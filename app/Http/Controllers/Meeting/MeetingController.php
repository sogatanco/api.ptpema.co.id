<?php

namespace App\Http\Controllers\Meeting;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\Meeting\Zoom;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    protected function getZoomClient(): Client
    {
        return new Client([
            'base_uri' => 'https://api.zoom.us/v2/',
            'timeout' => 30,
        ]);
    }

    protected function getZoomAccessToken(): string
    {
        $clientId = env('ZOOM_CLIENT_ID');
        $clientSecret = env('ZOOM_CLIENT_SECRET');
        $accountId = env('ZOOM_ACCOUNT_ID');

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
        $configuredUser = env('ZOOM_USER_ID');

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

        if (empty($topic) || empty($startTime)) {
            return new PostResource(false, 'topic and start_time are required', []);
        }

        $payload = [
            'topic' => $topic,
            'type' => 2,
            'start_time' => $startTime,
            'duration' => $duration,
            'timezone' => $request->input('timezone', 'Asia/Jakarta'),
            'agenda' => $agenda,
            'settings' => [
                'host_video' => (bool) $request->input('host_video', true),
                'participant_video' => (bool) $request->input('participant_video', true),
                'join_before_host' => false,
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

        $zoom = new Zoom();
        $zoom->topic = $meeting['topic'] ?? $topic;
        $zoom->link = $meeting['join_url'] ?? null;
        $zoom->meeting_id = $meeting['id'] ?? null;
        $zoom->password = $meeting['password'] ?? null;
        $zoom->start_time = $meeting['start_time'] ?? $startTime;
        $zoom->end_time = $this->calculateEndTime($startTime, $duration);
        $zoom->created_by = Employe::employeId();

        if ($zoom->save()) {
            return new PostResource(true, 'Zoom booked successfully', $zoom);
        }

        return new PostResource(false, 'Zoom meeting created on Zoom but failed to save to database', []);
    }

    protected function calculateEndTime(string $startTime, int $duration): ?string
    {
        try {
            return Carbon::parse($startTime)->addMinutes($duration)->toDateTimeString();
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
