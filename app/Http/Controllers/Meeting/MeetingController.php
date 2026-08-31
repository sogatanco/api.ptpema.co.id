<?php

namespace App\Http\Controllers\Meeting;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\Structure;
use App\Models\Meeting\BookingRoom;
use App\Models\Meeting\Zoom;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        $payload = [
            'topic' => $topic,
            'type' => 2,
            'use_pmi' => true,
            'start_time' => $startTime,
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
            return new PostResource(true, 'Zoom booked successfully', $zoom);
        }

        return new PostResource(false, 'Zoom meeting created on Zoom but failed to save to database', []);
    }

    protected function createZoomMeeting(string $topic, string $startTime, int $duration, ?string $agenda = null, ?string $timezone = null): array
    {
        $payload = [
            'topic' => $topic,
            'type' => 2,
            'use_pmi' => true,
            'start_time' => $startTime,
            'duration' => $duration,
            'timezone' => $timezone ?? config('app.timezone', 'Asia/Jakarta'),
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
            'room' => $room,
            'zoom_id' => $zoomId,
            'created_by' => Employe::employeId(),
        ]);

        if ((int) $consumptionRequired === 1) {
            $this->sendConsumptionNotification($booking);
        }

        return new PostResource(true, 'Meeting booked successfully', $booking);
    }

    public function listMeetingBookings()
    {
        $bookings = BookingRoom::with('zoom')
            ->whereNull('canceled_at')
            ->orderBy('start_time', 'asc')
            ->get();

        foreach ($bookings as $booking) {
            $booking->created_by_name = Employe::where('employe_id', $booking->created_by)->value('first_name');
            $booking->zoom_details = null;

            if ($booking->zoom) {
                $booking->zoom_details = [
                    'id' => $booking->zoom->meeting_id,
                    'link' => $booking->zoom->link,
                    'password' => $booking->zoom->password,
                ];
            }
        }

        foreach ($bookings as $booking) {
            $booking->created_by_name = Employe::where('employe_id', $booking->created_by)->value('first_name');
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

        return new PostResource(true, 'Meeting booking canceled successfully', $booking);
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

        foreach ($data as $item) {
            $item->created_by_name = Employe::where('employe_id', $item->created_by)->value('first_name');
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
            return new PostResource(true, 'Zoom canceled successfully', $zoom);
        }

        return new PostResource(false, 'Zoom canceled in Zoom but failed to update database', []);
    }
}
