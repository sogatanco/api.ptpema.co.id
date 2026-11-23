<?php

namespace App\Http\Controllers\Meeting;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\Structure;
use App\Models\Meeting\BlokirRuang;
use App\Models\Meeting\BookingRoom;
use App\Models\Meeting\Zoom;
use App\Services\Notifikasi\Hub;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MeetingController extends Controller
{
    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    protected function getZoomClient(): Client
    {
        return new Client([
            'base_uri' => 'https://api.zoom.us/v2/',
            'timeout' => 30,
        ]);
    }

    protected function resolveZoomCredentials(): array
    {
        $clientId = config('services.zoom.client_id') ?? env('ZOOM_CLIENT_ID');
        $clientSecret = config('services.zoom.client_secret') ?? env('ZOOM_CLIENT_SECRET');
        $accountId = config('services.zoom.account_id') ?? env('ZOOM_ACCOUNT_ID');

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'account_id' => $accountId,
        ];
    }

    protected function getZoomAccessToken(): string
    {
        $credentials = $this->resolveZoomCredentials();
        $clientId = $credentials['client_id'];
        $clientSecret = $credentials['client_secret'];
        $accountId = $credentials['account_id'];

        if (empty($clientId) || empty($clientSecret) || empty($accountId)) {
            throw new \RuntimeException('ZOOM_CLIENT_ID, ZOOM_CLIENT_SECRET, and ZOOM_ACCOUNT_ID must be configured.');
        }

        $client = new Client([
            'base_uri' => 'https://zoom.us/',
            'timeout' => 30,
        ]);

        $response = $client->request('POST', 'oauth/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'grant_type' => 'account_credentials',
                'account_id' => $accountId,
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);

        if (empty($body['access_token'])) {
            throw new \RuntimeException('Failed to obtain Zoom access token.');
        }

        return $body['access_token'];
    }

    protected function zoomRequest(string $method, string $uri, array $payload = []): array
    {
        $client = $this->getZoomClient();
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getZoomAccessToken(),
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($payload)) {
            $options['json'] = $payload;
        }

        try {
            $response = $client->request($method, $uri, $options);
            $body = (string) $response->getBody();

            return $body !== '' ? json_decode($body, true) : [];
        } catch (GuzzleException $e) {
            $responseBody = '';
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
            }

            $decodedBody = $responseBody !== '' ? json_decode($responseBody, true) : [];
            $message = $decodedBody['message'] ?? $e->getMessage();
            $code = $decodedBody['code'] ?? null;

            throw new \RuntimeException(($code ? 'Zoom error ' . $code . ': ' : '') . $message);
        }
    }

    protected function resolveZoomUserPath(): string
    {
        $configuredUser = config('services.zoom.user_id') ?? env('ZOOM_USER_ID');

        if (!empty($configuredUser)) {
            return 'users/' . $configuredUser;
        }

        return 'users/me';
    }

    public function bookZoom(Request $request)
    {
        $topic = $request->input('topic');
        $startTime = $request->input('start_time');
        $duration = (int) $request->input('duration', 60);
        $agenda = $request->input('agenda', $topic);
        $timezone = $request->input('timezone', config('app.timezone', 'Asia/Jakarta'));

        if (empty($topic) || empty($startTime)) {
            return new PostResource(false, 'topic and start_time are required', []);
        }

        // Zoom hanya membaca waktu ISO 8601 lengkap dengan detik (yyyy-MM-ddTHH:mm:ss) bersama timezone;
        // nilai dari input datetime-local ("2026-11-16T14:00") tidak terbaca sehingga rapat dijadwalkan di jam saat dibuat.
        try {
            $mulaiIso = Carbon::parse($startTime, $timezone ?: config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d\TH:i:s');
        } catch (\Exception $e) {
            return new PostResource(false, 'Invalid start_time format', []);
        }

        $payload = [
            'topic' => $topic,
            'type' => 2,
            'use_pmi' => true,
            'start_time' => $mulaiIso,
            'duration' => $duration,
            'timezone' => $timezone,
            'agenda' => $agenda,
            'settings' => [
                'host_video' => (bool) $request->input('host_video', true),
                'participant_video' => (bool) $request->input('participant_video', true),
                'join_before_host' => true,
                'mute_upon_entry' => true,
                'waiting_room' => false,
            ],
        ];

        try {
            $userPath = $this->resolveZoomUserPath();
            $meeting = $this->zoomRequest('POST', $userPath . '/meetings', $payload);
        } catch (GuzzleException | \RuntimeException $e) {
            return new PostResource(false, 'Failed to create Zoom meeting: ' . $e->getMessage(), []);
        }

        $normalizedStartTime = $this->normalizeDateTime($startTime, $timezone);

        $zoom = new Zoom();
        $zoom->topic = $meeting['topic'] ?? $topic;
        $zoom->link = $meeting['join_url'] ?? null;
        $zoom->meeting_id = $meeting['id'] ?? null;
        $zoom->password = $meeting['password'] ?? null;
        $zoom->start_time = $normalizedStartTime;
        $zoom->end_time = $this->calculateEndTime($normalizedStartTime ?? $startTime, $duration, $timezone);
        $zoom->created_by = Employe::employeId();

        if ($zoom->save()) {
            // Zoom tanpa booking ruangan: kirim undangan rapat Zoom ke peserta yang dipilih
            $this->tambahUndangan('zoom', (int) $zoom->id, $request->input('invitees', []), $zoom->topic ?: $topic, $zoom->start_time, $zoom->end_time, null, $zoom);

            return new PostResource(true, 'Zoom booked successfully', $zoom);
        }

        return new PostResource(false, 'Zoom meeting created on Zoom but failed to save to database', []);
    }

    protected function createZoomMeeting(string $topic, string $startTime, int $duration, ?string $agenda = null, ?string $timezone = null): array
    {
        $zona = $timezone ?: config('app.timezone', 'Asia/Jakarta');
        // Zoom hanya membaca waktu berformat ISO 8601 (yyyy-MM-ddTHH:mm:ss) bersama timezone;
        // format dengan spasi diabaikan sehingga rapat langsung dijadwalkan "sekarang" (jam saat dibuat).
        $mulaiIso = Carbon::parse($startTime, $zona)->format('Y-m-d\TH:i:s');

        $payload = [
            'topic' => $topic,
            'type' => 2,
            'use_pmi' => true,
            'start_time' => $mulaiIso,
            'duration' => $duration,
            'timezone' => $zona,
            'agenda' => $agenda ?? $topic,
            'settings' => [
                'host_video' => false,
                'participant_video' => false,
                'join_before_host' => true,
                'mute_upon_entry' => true,
                'waiting_room' => false,
            ],
        ];

        $userPath = $this->resolveZoomUserPath();

        return $this->zoomRequest('POST', $userPath . '/meetings', $payload);
    }

    protected function timeRangesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $rangeAStart = Carbon::parse($startA);
        $rangeAEnd = Carbon::parse($endA);
        $rangeBStart = Carbon::parse($startB);
        $rangeBEnd = Carbon::parse($endB);

        return $rangeAStart->lt($rangeBEnd) && $rangeAEnd->gt($rangeBStart);
    }

    protected function getRoomConflict(string $room, string $startTime, string $endTime, ?int $ignoreId = null): ?BookingRoom
    {
        $query = BookingRoom::where('room', $room)
            ->whereNull('canceled_at');

        if (!is_null($ignoreId)) {
            $query->where('id', '!=', $ignoreId);
        }

        $bookings = $query->with('zoom')->get();

        foreach ($bookings as $booking) {
            if ($this->timeRangesOverlap($startTime, $endTime, (string) $booking->start_time, (string) $booking->end_time)) {
                return $booking;
            }
        }

        return null;
    }

    protected function getZoomConflict(string $startTime, string $endTime, ?int $ignoreBookingId = null, ?int $ignoreZoomId = null): ?BookingRoom
    {
        $query = BookingRoom::where('zoom_required', true)
            ->whereNull('canceled_at')
            ->whereNotNull('zoom_id');

        if (!is_null($ignoreBookingId)) {
            $query->where('id', '!=', $ignoreBookingId);
        }

        $bookings = $query->with('zoom')->get();

        foreach ($bookings as $booking) {
            if (!is_null($ignoreZoomId) && (int) $booking->zoom_id === (int) $ignoreZoomId) {
                continue;
            }

            $bookingStart = $booking->zoom->start_time ?? $booking->start_time;
            $bookingEnd = $booking->zoom->end_time ?? $booking->end_time;

            if ($this->timeRangesOverlap($startTime, $endTime, (string) $bookingStart, (string) $bookingEnd)) {
                return $booking;
            }
        }

        return null;
    }

    protected function sendConsumptionNotification(BookingRoom $booking, string $event = 'booked'): void
    {
        $recipients = $this->getConsumptionRecipientPhones();

        if ($recipients->isEmpty()) {
            return;
        }

        $creatorName = Employe::where('employe_id', $booking->created_by)->value('first_name') ?: '-';
        $requesterPhoneRaw = (string) (Structure::where('employe_id', $booking->created_by)->value('phone_number') ?? '');
        $requesterPhones = $this->extractPhoneNumbers($requesterPhoneRaw);
        $requesterPhoneLabel = !empty($requesterPhones)
            ? implode(', ', array_map(function ($phone) {
                return preg_replace('/^62/', '0', (string) $phone);
            }, $requesterPhones))
            : '-';
        $eventLabel = $event === 'canceled' ? 'DIBATALKAN' : 'DIBUAT';
        $message = "Notifikasi Konsumsi Rapat ({$eventLabel})\n"
            . "Topik: {$booking->topic}\n"
            . "Ruangan: {$booking->room}\n"
            . "Waktu: {$booking->start_time} - {$booking->end_time}\n"
            . "Peserta: {$booking->participants}\n"
            . "Detail Konsumsi: " . ($booking->consumption_detail ?: '-') . "\n"
            . "Project / Renbis: " . ($booking->renbis_name ?: '-') . "\n"
            . "No HP PIC: {$requesterPhoneLabel}\n"
            . "PIC: {$creatorName}";

        foreach ($recipients as $number) {
            try {
                $this->whatsAppService->sendMessage((string) $number, $message);
            } catch (\Throwable $e) {
                Log::warning('Failed to send meeting consumption WA notification', [
                    'booking_id' => $booking->id,
                    'number' => $number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function adaTabelUndangan(): bool
    {
        try {
            return Schema::hasTable('meeting_undangan');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Undangan per rapat: [rapat_id => [['employe_id' => ..., 'nama' => ...], ...]].
     * $tipe 'ruang' memakai meetings.id, 'zoom' memakai zooms.id.
     */
    protected function ambilUndangan(string $tipe, array $rapatIds): array
    {
        if (!$rapatIds || !$this->adaTabelUndangan()) {
            return [];
        }

        $baris = DB::table('meeting_undangan')->where('tipe', $tipe)->whereIn('rapat_id', $rapatIds)->orderBy('id')->get();
        $nama = Employe::whereIn('employe_id', $baris->pluck('employe_id')->unique()->values())->pluck('first_name', 'employe_id');

        $hasil = [];
        foreach ($baris as $b) {
            $hasil[$b->rapat_id][] = ['employe_id' => $b->employe_id, 'nama' => $nama->get($b->employe_id) ?: $b->employe_id];
        }

        return $hasil;
    }

    /**
     * Simpan peserta yang diundang lalu kirim undangan (notifikasi bel + push + WhatsApp lewat Hub) hanya ke yang baru.
     * Rapat dengan Zoom menyertakan link, ID, dan passcode; Zoom tanpa ruangan dikirim sebagai undangan rapat Zoom.
     * Kegagalan kirim tidak membatalkan booking. Mengembalikan jumlah peserta baru.
     */
    protected function tambahUndangan(string $tipe, int $rapatId, $undangan, string $topic, ?string $mulai, ?string $selesai, ?string $ruangan, ?Zoom $zoom): int
    {
        $daftar = array_values(array_unique(array_filter(array_map('strval', (array) $undangan), fn ($x) => $x !== '')));
        $daftar = array_slice($daftar, 0, 300);
        $pengundangId = Employe::employeId();

        // pengundang tidak mengundang dirinya sendiri
        $daftar = array_values(array_filter($daftar, fn ($x) => $x !== (string) $pengundangId));

        if (!$daftar || !$this->adaTabelUndangan()) {
            return 0;
        }

        try {
            $sudah = DB::table('meeting_undangan')->where('tipe', $tipe)->where('rapat_id', $rapatId)->pluck('employe_id')->all();
            // hanya karyawan yang benar-benar ada
            $baru = array_values(Employe::whereIn('employe_id', array_diff($daftar, $sudah))->pluck('employe_id')->all());

            if (!$baru) {
                return 0;
            }

            DB::table('meeting_undangan')->insert(array_map(fn ($id) => [
                'tipe' => $tipe,
                'rapat_id' => $rapatId,
                'employe_id' => $id,
                'diundang_oleh' => $pengundangId,
                'created_at' => now(),
            ], $baru));

            $pengundang = Employe::where('employe_id', $pengundangId)->value('first_name') ?: 'Rekan kerja';
            $waktu = ($mulai ? Carbon::parse($mulai)->locale('id')->translatedFormat('l, d F Y H:i') : '-')
                . ($selesai ? ' - ' . Carbon::parse($selesai)->format('H:i') : '');

            $baris = [];
            $baris[] = "{$pengundang} mengundang Anda ke rapat \"{$topic}\".";
            $baris[] = 'Waktu: ' . $waktu . ' WIB';
            if ($ruangan) {
                $baris[] = 'Ruangan: ' . $ruangan;
            }
            if ($zoom && $zoom->link) {
                $baris[] = 'Zoom: ' . $zoom->link;
                if ($zoom->meeting_id) {
                    $baris[] = 'Meeting ID: ' . $zoom->meeting_id;
                }
                if ($zoom->password) {
                    $baris[] = 'Passcode: ' . $zoom->password;
                }
            }

            Hub::kirim($baru, $pengundangId, [
                'modul' => 'meeting',
                'event' => 'meeting.undangan',
                'judul' => ($ruangan ? 'Undangan Rapat: ' : 'Undangan Rapat Zoom: ') . $topic,
                'isi' => implode("\n", $baris),
                'url' => 'book-meeting',
                'ref' => ($tipe === 'ruang' ? 'rapat-' : 'zoom-') . $rapatId,
                'data' => ['ruangan' => $ruangan, 'zoom_link' => $zoom?->link, 'mulai' => $mulai, 'selesai' => $selesai],
                'paksa' => true,
            ]);

            return count($baru);
        } catch (\Throwable $e) {
            Log::warning('Undangan rapat gagal disimpan/dikirim', ['topic' => $topic, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    /** Beri tahu peserta yang diundang (mis. rapat dibatalkan). Kegagalan kirim tidak membatalkan proses utama. */
    protected function kabarkanPeserta(array $employeIds, string $event, string $judul, string $isi, string $ref): void
    {
        $employeIds = array_values(array_unique(array_filter(array_map('strval', $employeIds))));

        if (!$employeIds) {
            return;
        }

        try {
            Hub::kirim($employeIds, Employe::employeId(), [
                'modul' => 'meeting',
                'event' => $event,
                'judul' => $judul,
                'isi' => $isi,
                'url' => 'book-meeting',
                'ref' => $ref,
                'paksa' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Pemberitahuan rapat gagal dikirim', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    protected function waktuRapat(?string $mulai, ?string $selesai): string
    {
        return ($mulai ? Carbon::parse($mulai)->locale('id')->translatedFormat('l, d F Y H:i') : '-')
            . ($selesai ? ' - ' . Carbon::parse($selesai)->format('H:i') : '') . ' WIB';
    }

    /** Peserta diundang pada rapat tertentu (employe_id saja). */
    protected function idPesertaDiundang(string $tipe, int $rapatId): array
    {
        if (!$this->adaTabelUndangan()) {
            return [];
        }

        return DB::table('meeting_undangan')->where('tipe', $tipe)->where('rapat_id', $rapatId)->pluck('employe_id')->all();
    }

    /** Pembuat rapat mencabut undangan satu peserta; peserta itu diberi tahu. */
    public function hapusUndanganRuang($id, $employeId)
    {
        $booking = BookingRoom::find($id);

        if (!$booking || !is_null($booking->canceled_at)) {
            return new PostResource(false, 'Rapat tidak ditemukan atau sudah dibatalkan', []);
        }

        if ((string) $booking->created_by !== (string) Employe::employeId()) {
            return new PostResource(false, 'Hanya pembuat rapat yang bisa mencabut undangan', []);
        }

        return $this->cabutUndangan('ruang', (int) $booking->id, (string) $employeId, $booking->topic, $booking->start_time, $booking->end_time);
    }

    public function hapusUndanganZoom($id, $employeId)
    {
        $zoom = Zoom::find($id);

        if (!$zoom || !is_null($zoom->canceled_at)) {
            return new PostResource(false, 'Zoom tidak ditemukan atau sudah dibatalkan', []);
        }

        if ((string) $zoom->created_by !== (string) Employe::employeId()) {
            return new PostResource(false, 'Hanya pembuat rapat yang bisa mencabut undangan', []);
        }

        $booking = BookingRoom::where('zoom_id', $zoom->id)->whereNull('canceled_at')->first();

        return $booking
            ? $this->cabutUndangan('ruang', (int) $booking->id, (string) $employeId, $booking->topic, $booking->start_time, $booking->end_time)
            : $this->cabutUndangan('zoom', (int) $zoom->id, (string) $employeId, $zoom->topic, $zoom->start_time, $zoom->end_time);
    }

    protected function cabutUndangan(string $tipe, int $rapatId, string $employeId, string $topic, ?string $mulai, ?string $selesai)
    {
        if (!$this->adaTabelUndangan()) {
            return new PostResource(false, 'Tabel undangan belum dibuat di database (jalankan db/2026-11-23/meeting_undangan.sql)', []);
        }

        $terhapus = DB::table('meeting_undangan')->where('tipe', $tipe)->where('rapat_id', $rapatId)->where('employe_id', $employeId)->delete();

        if ($terhapus) {
            $this->kabarkanPeserta(
                [$employeId],
                'meeting.undangan_dicabut',
                'Undangan Rapat Dicabut: ' . $topic,
                'Undangan Anda ke rapat "' . $topic . '" (' . $this->waktuRapat($mulai, $selesai) . ') dicabut oleh pembuat rapat.',
                ($tipe === 'ruang' ? 'rapat-' : 'zoom-') . $rapatId
            );
        }

        return new PostResource(true, $terhapus ? 'Undangan dicabut' : 'Peserta tidak ada dalam daftar undangan', [
            'undangan' => $this->ambilUndangan($tipe, [$rapatId])[$rapatId] ?? [],
        ]);
    }

    /** Pembuat rapat menambah undangan (tag karyawan lagi) pada rapat yang sudah dibuat. */
    public function tambahUndanganRuang(Request $request, $id)
    {
        $booking = BookingRoom::with('zoom')->find($id);

        if (!$booking || !is_null($booking->canceled_at)) {
            return new PostResource(false, 'Rapat tidak ditemukan atau sudah dibatalkan', []);
        }

        if ((string) $booking->created_by !== (string) Employe::employeId()) {
            return new PostResource(false, 'Hanya pembuat rapat yang bisa menambah undangan', []);
        }

        if (!$this->adaTabelUndangan()) {
            return new PostResource(false, 'Tabel undangan belum dibuat di database (jalankan db/2026-11-23/meeting_undangan.sql)', []);
        }

        $jumlah = $this->tambahUndangan('ruang', (int) $booking->id, $request->input('invitees', []), $booking->topic, $booking->start_time, $booking->end_time, $booking->room, $booking->zoom);

        return new PostResource(true, $jumlah > 0 ? "Undangan dikirim ke {$jumlah} peserta" : 'Tidak ada peserta baru untuk diundang', [
            'baru' => $jumlah,
            'undangan' => $this->ambilUndangan('ruang', [(int) $booking->id])[$booking->id] ?? [],
        ]);
    }

    /** Pembuat Zoom menambah undangan. Zoom yang melekat pada booking ruangan memakai daftar undangan booking itu. */
    public function tambahUndanganZoom(Request $request, $id)
    {
        $zoom = Zoom::find($id);

        if (!$zoom || !is_null($zoom->canceled_at)) {
            return new PostResource(false, 'Zoom tidak ditemukan atau sudah dibatalkan', []);
        }

        if ((string) $zoom->created_by !== (string) Employe::employeId()) {
            return new PostResource(false, 'Hanya pembuat rapat yang bisa menambah undangan', []);
        }

        if (!$this->adaTabelUndangan()) {
            return new PostResource(false, 'Tabel undangan belum dibuat di database (jalankan db/2026-11-23/meeting_undangan.sql)', []);
        }

        $booking = BookingRoom::where('zoom_id', $zoom->id)->whereNull('canceled_at')->first();

        if ($booking) {
            $jumlah = $this->tambahUndangan('ruang', (int) $booking->id, $request->input('invitees', []), $booking->topic, $booking->start_time, $booking->end_time, $booking->room, $zoom);
            $undangan = $this->ambilUndangan('ruang', [(int) $booking->id])[$booking->id] ?? [];
        } else {
            $jumlah = $this->tambahUndangan('zoom', (int) $zoom->id, $request->input('invitees', []), $zoom->topic, $zoom->start_time, $zoom->end_time, null, $zoom);
            $undangan = $this->ambilUndangan('zoom', [(int) $zoom->id])[$zoom->id] ?? [];
        }

        return new PostResource(true, $jumlah > 0 ? "Undangan dikirim ke {$jumlah} peserta" : 'Tidak ada peserta baru untuk diundang', [
            'baru' => $jumlah,
            'undangan' => $undangan,
        ]);
    }

    protected function getConsumptionRecipientPhones()
    {
        return Structure::query()
            ->select('roles', 'phone_number')
            ->whereNotNull('phone_number')
            ->get()
            ->filter(function ($item) {
                $roles = $this->parseRolesValue($item->roles);

                return in_array('PICKonsumsi', $roles, true);
            })
            ->flatMap(function ($item) {
                return $this->extractPhoneNumbers((string) $item->phone_number);
            })
            ->filter(function ($phone) {
                return !empty($phone);
            })
            ->unique()
            ->values();
    }

    protected function parseRolesValue($roles): array
    {
        if (is_array($roles)) {
            return $roles;
        }

        if (is_string($roles) && $roles !== '') {
            $decoded = json_decode($roles, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return array_values(array_filter(array_map('trim', explode(',', $roles))));
        }

        return [];
    }

    protected function extractPhoneNumbers(string $rawPhone): array
    {
        if ($rawPhone === '') {
            return [];
        }

        $parts = preg_split('/[;,|\s]+/', $rawPhone);

        return array_values(array_filter(array_map('trim', $parts)));
    }

    public function bookMeetingRoom(Request $request)
    {
        $topic = $request->input('topic');
        $participants = (int) $request->input('participants', 0);
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');
        $zoomRequired = $request->input('zoom');
        $consumptionRequired = $request->input('consumption');
        $consumptionDetail = $request->input('consumption_detail');
        $renbisId = $request->input('renbis_id');
        $room = $request->input('room');
        $agenda = $request->input('agenda', $topic);
        $timezone = $request->input('timezone', config('app.timezone', 'Asia/Jakarta'));

        if (empty($topic) || empty($startTime) || empty($endTime) || empty($room)) {
            return new PostResource(false, 'topic, start_time, end_time and room are required', []);
        }

        $normalizedStart = $this->normalizeDateTime($startTime, $timezone);
        $normalizedEnd = $this->normalizeDateTime($endTime, $timezone);

        if (empty($normalizedStart) || empty($normalizedEnd)) {
            return new PostResource(false, 'Invalid start_time or end_time format', []);
        }

        if (Carbon::parse($normalizedEnd)->lessThanOrEqualTo(Carbon::parse($normalizedStart))) {
            return new PostResource(false, 'end_time must be after start_time', []);
        }

        $renbisName = null;

        if ((int) $consumptionRequired === 1) {
            $renbis = empty($renbisId) ? null : \App\Models\Sppd\Renbis::find($renbisId);

            if (!$renbis) {
                return new PostResource(false, 'Project / renbis wajib dipilih untuk permintaan konsumsi', []);
            }

            $renbisName = $renbis->renbis;
        } else {
            $renbisId = null;
        }

        // ruangan diblokir Sekretaris Direksi: tidak bisa dibooking pada rentang itu
        $blokir = $this->getBlokirConflict($room, $normalizedStart, $normalizedEnd);

        if ($blokir) {
            return new PostResource(false, 'Ruangan ' . $room . ' dipakai direksi pada ' . Carbon::parse($blokir->start_time)->format('d-m-Y H:i') . ' s.d. ' . Carbon::parse($blokir->end_time)->format('d-m-Y H:i') . ($blokir->alasan ? ' (' . $blokir->alasan . ')' : '') . ', sehingga tidak bisa dibooking pada waktu tersebut.', [
                'conflict_blokir_id' => $blokir->id,
                'conflict_room' => $blokir->room,
                'conflict_start_time' => $blokir->start_time,
                'conflict_end_time' => $blokir->end_time,
            ]);
        }

        $roomConflict = $this->getRoomConflict($room, $normalizedStart, $normalizedEnd);

        if ($roomConflict) {
            return new PostResource(false, 'Room is already booked in the selected time range', [
                'conflict_booking_id' => $roomConflict->id,
                'conflict_room' => $roomConflict->room,
                'conflict_start_time' => $roomConflict->start_time,
                'conflict_end_time' => $roomConflict->end_time,
            ]);
        }

        $zoomId = null;
        $zoomUndangan = null;

        if ($zoomRequired) {
            $zoomConflict = $this->getZoomConflict($normalizedStart, $normalizedEnd);

            if ($zoomConflict) {
                return new PostResource(false, 'Zoom meeting time conflicts with another meeting', [
                    'conflict_booking_id' => $zoomConflict->id,
                    'conflict_topic' => $zoomConflict->topic,
                    'conflict_start_time' => $zoomConflict->zoom?->start_time ?? $zoomConflict->start_time,
                    'conflict_end_time' => $zoomConflict->zoom?->end_time ?? $zoomConflict->end_time,
                ]);
            }

            try {
                $duration = Carbon::parse($normalizedStart, $timezone)->diffInMinutes(Carbon::parse($normalizedEnd, $timezone));
                $meeting = $this->createZoomMeeting($topic, $normalizedStart, $duration, $agenda, $timezone);

                $zoom = Zoom::create([
                    'topic' => $meeting['topic'] ?? $topic,
                    'link' => $meeting['join_url'] ?? null,
                    'meeting_id' => $meeting['id'] ?? null,
                    'password' => $meeting['password'] ?? null,
                    'start_time' => $normalizedStart,
                    'end_time' => $normalizedEnd,
                    'created_by' => Employe::employeId(),
                ]);

                $zoomId = $zoom->id;
                $zoomUndangan = $zoom;
            } catch (GuzzleException | \RuntimeException $e) {
                return new PostResource(false, 'Failed to create Zoom meeting: ' . $e->getMessage(), []);
            }
        }

        $booking = BookingRoom::create([
            'topic' => $topic,
            'participants' => $participants,
            'start_time' => $normalizedStart,
            'end_time' => $normalizedEnd,
            'zoom_required' => $zoomRequired,
            'consumption_required' => $consumptionRequired,
            'consumption_detail' => $consumptionDetail,
            'renbis_id' => $renbisId,
            'renbis_name' => $renbisName,
            'room' => $room,
            'zoom_id' => $zoomId,
            'created_by' => Employe::employeId(),
        ]);

        if ((int) $consumptionRequired === 1) {
            $this->sendConsumptionNotification($booking);
        }

        // undangan ke peserta yang dipilih (link Zoom ikut dikirim bila rapat memakai Zoom)
        $this->tambahUndangan('ruang', (int) $booking->id, $request->input('invitees', []), $topic, $normalizedStart, $normalizedEnd, $room, $zoomUndangan);

        return new PostResource(true, 'Meeting booked successfully', $booking);
    }

    public function listMeetingBookings()
    {
        $bookings = BookingRoom::with('zoom')
            ->whereNull('canceled_at')
            ->orderBy('start_time', 'asc')
            ->get();

        // satu query untuk semua nama pembuat (sebelumnya satu query per rapat, dua kali)
        $namaPembuat = Employe::whereIn('employe_id', $bookings->pluck('created_by')->filter()->unique()->values())
            ->pluck('first_name', 'employe_id');

        $undanganRuang = $this->ambilUndangan('ruang', $bookings->pluck('id')->all());

        foreach ($bookings as $booking) {
            $booking->created_by_name = $namaPembuat->get($booking->created_by);
            $booking->undangan = $undanganRuang[$booking->id] ?? [];
            $booking->zoom_details = null;

            if ($booking->zoom) {
                $booking->zoom_details = [
                    'id' => $booking->zoom->meeting_id,
                    'link' => $booking->zoom->link,
                    'password' => $booking->zoom->password,
                ];
            }
        }

        return new PostResource(true, 'success', $bookings);
    }

    public function cancelMeetingBooking($id)
    {
        $booking = BookingRoom::with('zoom')->find($id);

        if (!$booking) {
            return new PostResource(false, 'Meeting booking not found', []);
        }

        if (!is_null($booking->canceled_at)) {
            return new PostResource(false, 'Meeting booking already canceled', []);
        }

        if ($booking->zoom_required && $booking->zoom && is_null($booking->zoom->canceled_at) && !empty($booking->zoom->meeting_id)) {
            try {
                $this->zoomRequest('DELETE', 'meetings/' . $booking->zoom->meeting_id);
                $booking->zoom->canceled_at = now();
                $booking->zoom->save();
            } catch (GuzzleException | \RuntimeException $e) {
                return new PostResource(false, 'Failed to cancel Zoom meeting: ' . $e->getMessage(), []);
            }
        }

        $booking->canceled_at = now();
        $booking->save();

        if ((int) $booking->consumption_required === 1) {
            $this->sendConsumptionNotification($booking, 'canceled');
        }

        // peserta yang diundang diberi tahu bahwa rapat dibatalkan
        $this->kabarkanPeserta(
            $this->idPesertaDiundang('ruang', (int) $booking->id),
            'meeting.dibatalkan',
            'Rapat Dibatalkan: ' . $booking->topic,
            'Rapat "' . $booking->topic . '" (' . $this->waktuRapat($booking->start_time, $booking->end_time) . ', ruangan ' . $booking->room . ') dibatalkan.',
            'rapat-' . $booking->id
        );

        return new PostResource(true, 'Meeting booking canceled successfully', $booking);
    }

    /** ruangan yang bisa diblokir (sama dengan pilihan di form booking) */
    private const RUANG_RAPAT = ['Growth', 'Harmoni', 'Kopiah', 'Internasional'];

    /** tabel blokir (db/2026-11-20/blokir_ruang_rapat.sql) tersedia; bila belum, booking berjalan seperti biasa tanpa blokir */
    protected function adaTabelBlokir(): bool
    {
        if (\Illuminate\Support\Facades\Cache::get('meeting_blokir_ada') === true) {
            return true;
        }
        $ada = Schema::hasTable('meeting_blokir');
        if ($ada) {
            \Illuminate\Support\Facades\Cache::put('meeting_blokir_ada', true, 300);
        }

        return $ada;
    }

    /** hanya karyawan berjabatan Sekretaris Direksi yang boleh memblokir ruangan */
    protected function adalahSekretarisDireksi(): bool
    {
        $me = Employe::employeId();

        return $me && Structure::where('employe_id', $me)->where('position_name', 'like', 'Sekretaris Direksi%')->exists();
    }

    protected function getBlokirConflict(string $room, string $startTime, string $endTime, ?int $ignoreId = null): ?BlokirRuang
    {
        if (!$this->adaTabelBlokir()) {
            return null;
        }
        $query = BlokirRuang::where('room', $room)->whereNull('canceled_at');
        if (!is_null($ignoreId)) {
            $query->where('id', '!=', $ignoreId);
        }
        foreach ($query->get() as $blokir) {
            if ($this->timeRangesOverlap($startTime, $endTime, (string) $blokir->start_time, (string) $blokir->end_time)) {
                return $blokir;
            }
        }

        return null;
    }

    /**
     * Blokir yang masih berlaku atau akan datang (untuk halaman booking rapat; TIDAK dipakai /jadwal-rapat) dan
     * apakah pengguna ini boleh memblokir (Sekretaris Direksi).
     */
    public function listBlokir()
    {
        $boleh = $this->adalahSekretarisDireksi();
        $data = collect();
        if ($this->adaTabelBlokir()) {
            $data = BlokirRuang::whereNull('canceled_at')->where('end_time', '>=', now())->orderBy('start_time')->get();
            $nama = Employe::whereIn('employe_id', $data->pluck('created_by')->filter()->unique()->values())->pluck('first_name', 'employe_id');
            foreach ($data as $b) {
                $b->created_by_name = $nama->get($b->created_by);
            }
        }

        return new PostResource(true, 'success', ['boleh_blokir' => $boleh, 'blokir' => $data->values()]);
    }

    /** Sekretaris Direksi memblokir ruangan dari jam sampai jam; ditolak bila bentrok dengan booking atau blokir lain. */
    public function blokirRuang(Request $request)
    {
        if (!$this->adalahSekretarisDireksi()) {
            return response()->json(['status' => false, 'message' => 'Hanya Sekretaris Direksi yang dapat menandai ruang rapat dipakai direksi.'], 403);
        }
        if (!$this->adaTabelBlokir()) {
            return response()->json(['status' => false, 'message' => 'Fitur ruang dipakai direksi belum aktif di database: jalankan db/2026-11-20/blokir_ruang_rapat.sql terlebih dulu.'], 422);
        }
        $request->validate([
            'room' => 'required|string|in:' . implode(',', self::RUANG_RAPAT),
            'start_time' => 'required',
            'end_time' => 'required',
            'alasan' => 'nullable|string|max:255',
        ]);
        $timezone = $request->input('timezone', config('app.timezone', 'Asia/Jakarta'));
        $mulai = $this->normalizeDateTime($request->input('start_time'), $timezone);
        $selesai = $this->normalizeDateTime($request->input('end_time'), $timezone);
        if (empty($mulai) || empty($selesai)) {
            return new PostResource(false, 'Format waktu mulai atau selesai tidak valid', []);
        }
        if (Carbon::parse($selesai)->lessThanOrEqualTo(Carbon::parse($mulai))) {
            return new PostResource(false, 'Waktu selesai harus setelah waktu mulai', []);
        }
        $room = $request->input('room');

        $booking = $this->getRoomConflict($room, $mulai, $selesai);
        if ($booking) {
            return new PostResource(false, 'Sudah ada booking "' . $booking->topic . '" pada ' . Carbon::parse($booking->start_time)->format('d-m-Y H:i') . ' s.d. ' . Carbon::parse($booking->end_time)->format('d-m-Y H:i') . ' di ruangan ini. Batalkan bookingnya dulu atau pilih waktu lain.', ['conflict_booking_id' => $booking->id]);
        }
        $lain = $this->getBlokirConflict($room, $mulai, $selesai);
        if ($lain) {
            return new PostResource(false, 'Ruangan ini sudah ditandai dipakai direksi pada ' . Carbon::parse($lain->start_time)->format('d-m-Y H:i') . ' s.d. ' . Carbon::parse($lain->end_time)->format('d-m-Y H:i') . '.', ['conflict_blokir_id' => $lain->id]);
        }

        $blokir = BlokirRuang::create([
            'room' => $room,
            'start_time' => $mulai,
            'end_time' => $selesai,
            'alasan' => trim((string) $request->input('alasan')) ?: null,
            'created_by' => Employe::employeId(),
        ]);

        return new PostResource(true, 'Ruangan ' . $room . ' ditandai dipakai direksi.', $blokir);
    }

    /** Membatalkan blokir (Sekretaris Direksi). */
    public function batalBlokir($id)
    {
        if (!$this->adalahSekretarisDireksi()) {
            return response()->json(['status' => false, 'message' => 'Hanya Sekretaris Direksi yang dapat membatalkan penandaan ruang dipakai direksi.'], 403);
        }
        $blokir = $this->adaTabelBlokir() ? BlokirRuang::find($id) : null;
        if (!$blokir) {
            return new PostResource(false, 'Data tidak ditemukan', []);
        }
        if (!is_null($blokir->canceled_at)) {
            return new PostResource(false, 'Sudah dibatalkan sebelumnya', []);
        }
        $blokir->canceled_at = now();
        $blokir->save();

        return new PostResource(true, 'Pemakaian ruangan oleh direksi dibatalkan.', $blokir);
    }

    protected function normalizeDateTime($value, ?string $timezone = null): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($value, $timezone ?: config('app.timezone', 'Asia/Jakarta'));

            if (!empty($timezone)) {
                $parsed = $parsed->setTimezone($timezone);
            }

            return $parsed->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function calculateEndTime($startTime, int $duration, ?string $timezone = null): ?string
    {
        $normalizedStartTime = $this->normalizeDateTime($startTime, $timezone);

        if (empty($normalizedStartTime)) {
            return null;
        }

        try {
            return Carbon::parse($normalizedStartTime, $timezone ?: config('app.timezone', 'Asia/Jakarta'))
                ->addMinutes($duration)
                ->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function listZoom()
    {
        $data = Zoom::whereNull('canceled_at')
            ->orderBy('start_time', 'asc')
            ->get();

        // Zoom yang melekat pada booking ruangan memakai undangan booking itu; Zoom tanpa ruangan punya undangan sendiri
        $bookingPerZoom = BookingRoom::whereIn('zoom_id', $data->pluck('id'))->whereNull('canceled_at')->pluck('id', 'zoom_id');
        $undanganRuang = $this->ambilUndangan('ruang', $bookingPerZoom->values()->all());
        $undanganZoom = $this->ambilUndangan('zoom', $data->pluck('id')->all());

        foreach ($data as $item) {
            $item->created_by_name = Employe::where('employe_id', $item->created_by)->value('first_name');
            $item->undangan = $bookingPerZoom->has($item->id)
                ? ($undanganRuang[$bookingPerZoom->get($item->id)] ?? [])
                : ($undanganZoom[$item->id] ?? []);
        }

        return new PostResource(true, 'success', $data);
    }

    public function cancelZoom($id)
    {
        $zoom = Zoom::find($id);

        if (!$zoom) {
            return new PostResource(false, 'Zoom not found', []);
        }

        if (!is_null($zoom->canceled_at)) {
            return new PostResource(false, 'Zoom already canceled', []);
        }

        if (empty($zoom->meeting_id)) {
            return new PostResource(false, 'Zoom meeting id is missing', []);
        }

        try {
            $this->zoomRequest('DELETE', 'meetings/' . $zoom->meeting_id);
        } catch (GuzzleException | \RuntimeException $e) {
            return new PostResource(false, 'Failed to cancel Zoom meeting: ' . $e->getMessage(), []);
        }

        $zoom->canceled_at = now();

        if ($zoom->save()) {
            // Zoom tanpa ruangan: rapat dibatalkan; Zoom milik booking ruangan: hanya link Zoom-nya yang batal
            $booking = BookingRoom::where('zoom_id', $zoom->id)->whereNull('canceled_at')->first();

            if ($booking) {
                $this->kabarkanPeserta(
                    $this->idPesertaDiundang('ruang', (int) $booking->id),
                    'meeting.zoom_dibatalkan',
                    'Link Zoom Dibatalkan: ' . $booking->topic,
                    'Link Zoom untuk rapat "' . $booking->topic . '" (' . $this->waktuRapat($booking->start_time, $booking->end_time) . ') dibatalkan. Informasi pengganti akan disampaikan oleh pembuat rapat.',
                    'rapat-' . $booking->id
                );
            } else {
                $this->kabarkanPeserta(
                    $this->idPesertaDiundang('zoom', (int) $zoom->id),
                    'meeting.dibatalkan',
                    'Rapat Zoom Dibatalkan: ' . $zoom->topic,
                    'Rapat Zoom "' . $zoom->topic . '" (' . $this->waktuRapat($zoom->start_time, $zoom->end_time) . ') dibatalkan.',
                    'zoom-' . $zoom->id
                );
            }

            return new PostResource(true, 'Zoom canceled successfully', $zoom);
        }

        return new PostResource(false, 'Zoom canceled in Zoom but failed to update database', []);
    }
}
