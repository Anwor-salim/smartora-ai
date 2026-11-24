<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    protected string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = env('META_GRAPH_API_VERSION', 'v21.0');
    }

    /**
     * Resolve Graph API base URL depending on token type (Instagram Login IGAA... vs Facebook Page EAAG...).
     */
    protected function resolveGraphBase(string $token): string
    {
        $trimmed = trim($token);
        if (str_starts_with($trimmed, 'IG')) {
            return "https://graph.instagram.com/{$this->apiVersion}";
        }

        return "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Send a direct message (DM) to an Instagram user, automatically splitting replies > 950 chars.
     */
    public function sendDirectMessage(Company $company, string $recipientIgId, string $message): array
    {
        $accountId = trim((string) $company->instagram_account_id);
        $accessToken = trim((string) $company->instagram_access_token);

        if ($accountId === '' || $accessToken === '') {
            Log::warning("Instagram sendDirectMessage skipped: Missing credentials for company {$company->id}");
            return [
                'success' => false,
                'error' => 'Missing Instagram API credentials for this company',
            ];
        }

        $base = $this->resolveGraphBase($accessToken);
        $targetNode = str_starts_with($accessToken, 'IG') ? 'me' : $accountId;
        $url = "{$base}/{$targetNode}/messages";

        $chunks = $this->splitMessage($message, 950);
        $lastMessageId = null;

        foreach ($chunks as $chunk) {
            $payload = [
                'recipient' => ['id' => $recipientIgId],
                'message' => ['text' => $chunk],
            ];

            try {
                $response = Http::timeout(15)
                    ->withToken($accessToken)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->post($url, $payload);

                if (!$response->successful()) {
                    $error = $response->json()['error']['message'] ?? $response->body();
                    Log::error("Instagram sendDirectMessage failed for company {$company->id}: {$error}", [
                        'status' => $response->status(),
                        'recipient' => $recipientIgId,
                    ]);

                    return [
                        'success' => false,
                        'error' => $error,
                        'status_code' => $response->status(),
                    ];
                }

                $data = $response->json();
                $lastMessageId = $data['message_id'] ?? $lastMessageId;
            } catch (\Throwable $e) {
                Log::error("Instagram sendDirectMessage exception for company {$company->id}: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info("Instagram DM sent successfully to {$recipientIgId} for company {$company->id}", [
            'chunks' => count($chunks),
            'message_id' => $lastMessageId,
        ]);

        return [
            'success' => true,
            'message_id' => $lastMessageId,
            'chunks' => count($chunks),
        ];
    }

    /**
     * Send a private DM reply triggered by a user's comment on an Instagram post or reel.
     */
    public function sendPrivateReplyToComment(Company $company, string $commentId, string $dmText): array
    {
        $accountId = trim((string) $company->instagram_account_id);
        $accessToken = trim((string) $company->instagram_access_token);

        if ($accountId === '' || $accessToken === '') {
            return ['success' => false, 'error' => 'Missing Instagram credentials'];
        }

        $base = $this->resolveGraphBase($accessToken);
        $targetNode = str_starts_with($accessToken, 'IG') ? 'me' : $accountId;
        $url = "{$base}/{$targetNode}/messages";

        $payload = [
            'recipient' => ['comment_id' => $commentId],
            'message' => ['text' => mb_substr(trim($dmText), 0, 950)],
        ];

        try {
            $response = Http::timeout(15)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($url, $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            $error = $response->json()['error']['message'] ?? $response->body();
            Log::error("Instagram private reply to comment failed for company {$company->id}: {$error}");
            return ['success' => false, 'error' => $error];
        } catch (\Throwable $e) {
            Log::error("Instagram private reply exception for company {$company->id}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Post a public reply to an Instagram comment.
     */
    public function replyToComment(Company $company, string $commentId, string $publicReplyText): array
    {
        $accessToken = trim((string) $company->instagram_access_token);
        if ($accessToken === '' || trim($publicReplyText) === '') {
            return ['success' => false, 'error' => 'Missing token or reply text'];
        }

        $base = $this->resolveGraphBase($accessToken);
        $url = "{$base}/{$commentId}/replies";

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($url, ['message' => trim($publicReplyText)]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            $error = $response->json()['error']['message'] ?? $response->body();
            Log::warning("Instagram public comment reply failed for company {$company->id}: {$error}");
            return ['success' => false, 'error' => $error];
        } catch (\Throwable $e) {
            Log::warning("Instagram public comment reply exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify credentials against Meta Instagram Graph API and return account metadata.
     */
    public function verifyConnection(string $accountId, string $accessToken): array
    {
        $accountId = trim($accountId);
        $accessToken = trim($accessToken);

        if ($accessToken === '') {
            return [
                'success' => false,
                'error' => 'Access Token is required',
            ];
        }

        $base = $this->resolveGraphBase($accessToken);
        $node = ($accountId !== '' && !str_starts_with($accessToken, 'IG')) ? $accountId : ($accountId ?: 'me');
        $fields = str_starts_with($accessToken, 'IG')
            ? 'id,user_id,username,name,followers_count,profile_picture_url'
            : 'id,username,name,followers_count,profile_picture_url';

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$base}/{$node}", ['fields' => $fields]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            $error = $response->json()['error']['message'] ?? 'Failed to verify Instagram credentials';
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

    /**
     * Best-effort lookup of an Instagram sender's name/username. Never throws.
     */
    public function getSenderProfile(Company $company, string $senderIgId): ?array
    {
        $accessToken = trim((string) $company->instagram_access_token);
        if ($accessToken === '' || $senderIgId === '') {
            return null;
        }

        try {
            $base = $this->resolveGraphBase($accessToken);
            $response = Http::timeout(3)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$base}/{$senderIgId}", ['fields' => 'name,username']);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'name' => $data['name'] ?? null,
                    'username' => $data['username'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // Best-effort only
        }

        return null;
    }

    /**
     * Check whether 1-click Instagram OAuth App credentials are configured on the server.
     */
    public function isOAuthConfigured(): bool
    {
        return !empty(env('META_INSTAGRAM_APP_ID')) && !empty(env('META_INSTAGRAM_APP_SECRET'));
    }

    /**
     * Build the official Meta Instagram Business Login OAuth URL for 1-click connection.
     */
    public function buildOAuthUrl(Company $company): ?string
    {
        $appId = env('META_INSTAGRAM_APP_ID');
        if (empty($appId)) {
            return null;
        }

        $redirectUri = url('/company/instagram/oauth/callback');
        $state = Crypt::encryptString(json_encode([
            'company_id' => $company->id,
            'ts' => now()->timestamp,
        ]));

        $query = http_build_query([
            'enable_fb_login' => '0',
            'force_authentication' => '1',
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_manage_messages,instagram_business_manage_comments',
            'state' => $state,
        ]);

        return "https://www.instagram.com/oauth/authorize?{$query}";
    }

    /**
     * Exchange OAuth authorization code for a 60-day Long-Lived token and fetch account metadata.
     */
    public function exchangeOAuthCode(string $code): array
    {
        $appId = env('META_INSTAGRAM_APP_ID');
        $appSecret = env('META_INSTAGRAM_APP_SECRET');

        if (empty($appId) || empty($appSecret)) {
            return ['success' => false, 'error' => 'Instagram App ID / Secret not configured on server'];
        }

        $redirectUri = url('/company/instagram/oauth/callback');

        try {
            // 1. Exchange code for short-lived token
            $tokenResp = Http::asForm()->timeout(15)->post('https://api.instagram.com/oauth/access_token', [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

            if (!$tokenResp->successful()) {
                return [
                    'success' => false,
                    'error' => $tokenResp->json()['error_message'] ?? $tokenResp->body(),
                ];
            }

            $shortData = $tokenResp->json();
            $shortToken = $shortData['access_token'] ?? null;
            $igUserId = (string) ($shortData['user_id'] ?? '');

            if (!$shortToken) {
                return ['success' => false, 'error' => 'No access token returned by Instagram'];
            }

            // 2. Upgrade to 60-day Long-Lived token
            $longToken = $shortToken;
            $longResp = Http::timeout(15)->get('https://graph.instagram.com/access_token', [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => $appSecret,
                'access_token' => $shortToken,
            ]);

            if ($longResp->successful() && !empty($longResp->json()['access_token'])) {
                $longToken = $longResp->json()['access_token'];
            }

            // 3. Fetch profile details (account ID + username)
            $profileResp = Http::timeout(10)
                ->withToken($longToken)
                ->get("https://graph.instagram.com/{$this->apiVersion}/me", [
                    'fields' => 'id,user_id,username,name',
                ]);

            $profile = $profileResp->successful() ? $profileResp->json() : [];

            return [
                'success' => true,
                'access_token' => $longToken,
                'account_id' => (string) ($profile['user_id'] ?? $profile['id'] ?? $igUserId),
                'username' => $profile['username'] ?? null,
                'name' => $profile['name'] ?? null,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Split text cleanly into chunks within Instagram's 1000-char limit.
     */
    protected function splitMessage(string $text, int $maxLen = 950): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= $maxLen) {
            return [$text];
        }

        $chunks = [];
        $remaining = $text;

        while (mb_strlen($remaining) > $maxLen) {
            $slice = mb_substr($remaining, 0, $maxLen);
            $splitPos = mb_strrpos($slice, "\n");
            if ($splitPos === false || $splitPos < (int) ($maxLen * 0.4)) {
                $splitPos = mb_strrpos($slice, ' ');
            }
            if ($splitPos === false || $splitPos < (int) ($maxLen * 0.3)) {
                $splitPos = $maxLen;
            }

            $chunks[] = trim(mb_substr($remaining, 0, $splitPos));
            $remaining = trim(mb_substr($remaining, $splitPos));
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }
}
