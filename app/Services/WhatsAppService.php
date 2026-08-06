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