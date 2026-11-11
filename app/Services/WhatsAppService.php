<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class WhatsAppService
{
    protected Client $client;

    public function __construct()
    {
        $baseUri = config('services.whatsapp.base_url', 'https://app.pema.co.id/api/');

        $this->client = new Client([
            'base_uri' => rtrim($baseUri, '/') . '/',
            'timeout' => 30,
        ]);
    }

    public function sendMessage(string $number, string $message): array
    {
        $apiKey = config('services.whatsapp.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('WhatsApp API key is not configured.');
        }

        $normalizedNumber = $this->normalizePhoneNumber($number);

        if (empty($normalizedNumber)) {
            throw new RuntimeException('Invalid WhatsApp number.');
        }

        try {
            $response = $this->client->request('POST', 'send-message', [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'number' => $normalizedNumber,
                    'message' => $message,
                ],
            ]);

            $body = (string) $response->getBody();

            return $body !== '' ? (json_decode($body, true) ?: []) : [];
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to send WhatsApp message: ' . $e->getMessage());
        }
    }

    /**
     * Status gateway dari GET {base_url}/health. 'hidup' = gateway menjawab 2xx;
     * 'terhubung' = status sesi WhatsApp bila terbaca dari isi respons (null jika tidak dikenali).
     */
    public function health(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('wa_gateway_health', 15, function () {
            $apiKey = config('services.whatsapp.api_key');
            if (empty($apiKey)) {
                return ['hidup' => false, 'terhubung' => null, 'http' => null, 'respons' => null, 'pesan' => 'API key gateway WhatsApp belum diisi di server.'];
            }
            try {
                // alamat status tetap; hanya API key (rahasia) yang diambil dari konfigurasi server
                $res = (new Client(['timeout' => 10, 'http_errors' => false]))
                    ->get('https://app.pema.co.id/api/health', ['headers' => ['x-api-key' => $apiKey, 'Accept' => 'application/json']]);
            } catch (\Throwable $e) {
                return ['hidup' => false, 'terhubung' => null, 'http' => null, 'respons' => null, 'pesan' => 'Gateway tidak bisa dihubungi: ' . $e->getMessage()];
            }
            $kode = $res->getStatusCode();
            $isi = (string) $res->getBody();

            return [
                'hidup' => $kode >= 200 && $kode < 300,
                'terhubung' => $kode >= 200 && $kode < 300 ? $this->bacaTerhubung(json_decode($isi, true)) : false,
                'http' => $kode,
                'respons' => mb_substr($isi, 0, 500),
                'pesan' => $kode >= 200 && $kode < 300 ? null : "Gateway menjawab HTTP {$kode}",
            ];
        });
    }

    // cari penanda sesi tersambung di JSON health, apa pun bentuknya
    protected function bacaTerhubung($data): ?bool
    {
        if (!is_array($data)) {
            return null;
        }
        // bentuk respons gateway ini: {"ok":true,"whatsappReady":true,"timestamp":"..."}
        $kunciBool = ['whatsappready', 'connected', 'isconnected', 'ready', 'isready', 'whatsapp', 'wa', 'authenticated', 'online', 'logged_in', 'loggedin'];
        $kata = ['connected', 'ready', 'open', 'online', 'authenticated', 'ok', 'up'];
        foreach ($data as $k => $v) {
            $kunci = strtolower((string) $k);
            if (in_array($kunci, $kunciBool, true) && is_bool($v)) {
                return $v;
            }
            if (in_array($kunci, ['status', 'state', 'connection', 'whatsapp', 'wa', 'session'], true) && is_string($v)) {
                return in_array(strtolower($v), $kata, true);
            }
            if (is_array($v) && ($r = $this->bacaTerhubung($v)) !== null) {
                return $r;
            }
        }

        return null;
    }

    protected function normalizePhoneNumber(string $number): ?string
    {
        $clean = preg_replace('/\D+/', '', $number);

        if (empty($clean)) {
            return null;
        }

        if (strpos($clean, '0') === 0) {
            $clean = '62' . substr($clean, 1);
        }

        if (strpos($clean, '62') !== 0) {
            $clean = '62' . ltrim($clean, '0');
        }

        return strlen($clean) >= 10 ? $clean : null;
    }
}