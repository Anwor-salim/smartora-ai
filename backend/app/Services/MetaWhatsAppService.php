<?php

namespace App\Services;

use App\Models\Company;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppService
{
    protected string $graphApiVersion;

    public function __construct()
    {
        $this->graphApiVersion = env('META_GRAPH_API_VERSION', 'v21.0');
    }

    /**
     * Send a text message to a customer via Meta WhatsApp Cloud API.
     */
    public function sendText(Company $company, string $toPhone, string $message): array
    {
        $phoneNumberId = $company->meta_phone_number_id;
        $accessToken = $company->meta_access_token;

        if (empty($phoneNumberId) || empty($accessToken)) {
            Log::error("Meta WhatsApp sendText failed: Missing credentials for company {$company->id}");
            return [
                'success' => false,
                'error' => 'Missing Meta Cloud API credentials for this company',
            ];
        }

        // Clean recipient phone number (digits only)
        $cleanPhone = preg_replace('/\D/', '', $toPhone);

        $url = "https://graph.facebook.com/{$this->graphApiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];

        try {
            $response = Http::timeout(15)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Meta WhatsApp message sent successfully to {$cleanPhone} for company {$company->id}", [
                    'message_id' => $data['messages'][0]['id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                    'data' => $data,
                ];
            }

            $error = $response->json()['error']['message'] ?? $response->body();
            Log::error("Meta WhatsApp API error for company {$company->id}: " . $error, [
                'status' => $response->status(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error' => $error,
                'status_code' => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error("Meta WhatsApp HTTP exception for company {$company->id}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify credentials with Meta Graph API and retrieve phone details.
     */
    public function verifyConnection(string $phoneNumberId, string $accessToken): array
    {
        $phoneNumberId = trim($phoneNumberId);
        $accessToken = trim($accessToken);

        if (empty($phoneNumberId) || empty($accessToken)) {
            return [
                'success' => false,
                'error' => 'Phone Number ID and Access Token are required',
            ];
        }

        $url = "https://graph.facebook.com/{$this->graphApiVersion}/{$phoneNumberId}";

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url, [
                    'fields' => 'id,verified_name,display_phone_number,quality_rating,code_verification_status',
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            $error = $response->json()['error']['message'] ?? 'Failed to verify Meta credentials';
            return [
                'success' => false,
                'error' => $error,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
