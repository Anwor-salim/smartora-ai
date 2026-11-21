<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WahaWhatsAppService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('WAHA_API_URL', 'http://smartora-waha:3000'), '/');
        $this->apiKey = env('WAHA_API_KEY', 'smartora_secret_api_key_2026');
    }

    protected function client()
    {
        return Http::timeout(10)->withHeaders([
            'X-Api-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Get details of a session (status, me, pushName, etc.)
     */
    public function getSession(string $session = 'default'): ?array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/sessions/{$session}");
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error("WAHA getSession failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Check if the session is currently WORKING.
     */
    public function isWorking(string $session = 'default'): bool
    {
        $data = $this->getSession($session);
        return ($data['status'] ?? '') === 'WORKING';
    }

    /**
     * Get the connected phone number if connected.
     */
    public function getConnectedPhone(string $session = 'default'): ?string
    {
        $data = $this->getSession($session);
        if (($data['status'] ?? '') === 'WORKING' && !empty($data['me']['id'])) {
            $rawId = $data['me']['id'];
            $phone = explode('@', $rawId)[0];
            return preg_replace('/\D/', '', $phone);
        }

        return null;
    }

    /**
     * Get the push name / profile name.
     */
    public function getPushName(string $session = 'default'): ?string
    {
        $data = $this->getSession($session);
        return $data['me']['pushName'] ?? null;
    }

    /**
     * Get the real WhatsApp Web pairing QR code.
     * Returns base64 image data URI: 'data:image/png;base64,...' or null if not in SCAN_QR_CODE.
     */
    public function getQrCode(string $session = 'default'): ?string
    {
        try {
            $sessionData = $this->getSession($session);
            $status = $sessionData['status'] ?? '';

            if ($status === 'WORKING') {
                return null;
            }

            // If session is STOPPED or FAILED, start/restart it
            if (in_array($status, ['STOPPED', 'FAILED', ''])) {
                $this->restartSession($session);
                usleep(500000);
            }

            // Request QR as image from WAHA
            $response = Http::timeout(10)->withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'image/png',
            ])->get("{$this->baseUrl}/api/{$session}/auth/qr?format=image");

            if ($response->successful()) {
                $body = $response->body();
                if (str_starts_with($body, "\x89PNG") || str_starts_with($body, "\xFF\xD8\xFF")) {
                    return 'data:image/png;base64,' . base64_encode($body);
                }
            }

            // Fallback: check if raw string returned
            $rawResponse = $this->client()->get("{$this->baseUrl}/api/{$session}/auth/qr?format=raw");
            if ($rawResponse->successful()) {
                $raw = $rawResponse->json('qr') ?? $rawResponse->body();
                if ($raw && !str_contains($raw, 'error')) {
                    return "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" . urlencode($raw);
                }
            }
        } catch (\Throwable $e) {
            Log::error("WAHA getQrCode failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Start the session.
     */
    public function startSession(string $session = 'default'): bool
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/sessions/{$session}/start");
            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Restart the session to generate a fresh QR code.
     */
    public function restartSession(string $session = 'default'): bool
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/sessions/{$session}/restart");
            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Logout from WhatsApp.
     */
    public function logoutSession(string $session = 'default'): bool
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/sessions/{$session}/logout");
            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Send message via WAHA.
     */
    public function sendText(string $chatId, string $text, string $session = 'default'): bool
    {
        try {
            $cleanChatId = str_contains($chatId, '@') ? $chatId : (preg_replace('/\D/', '', $chatId) . '@c.us');
            $response = $this->client()->post("{$this->baseUrl}/api/sendText", [
                'session' => $session,
                'chatId' => $cleanChatId,
                'text' => $text,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WAHA sendText failed: " . $e->getMessage());
            return false;
        }
    }
}
