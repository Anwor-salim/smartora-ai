<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessengerService
{
    protected string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = env('META_GRAPH_API_VERSION', 'v21.0');
    }

    /**
     * Base URL for Facebook Graph API.
     */
    protected function graphBase(): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Send a direct message to a Facebook Messenger user (PSID), splitting replies > 1900 chars.
     */
    public function sendDirectMessage(Company $company, string $recipientPsid, string $message): array
    {
        $pageId = trim((string) $company->messenger_page_id);
        $accessToken = trim((string) $company->messenger_access_token);

        if ($pageId === '' || $accessToken === '') {
            Log::warning("Messenger sendDirectMessage skipped: Missing credentials for company {$company->id}");
            return [
                'success' => false,
                'error' => 'Missing Messenger API credentials for this company',
            ];
        }

        $url = $this->graphBase() . "/{$pageId}/messages";
        $chunks = $this->splitMessage($message, 1900);
        $lastMessageId = null;

        foreach ($chunks as $chunk) {
            $payload = [
                'messaging_type' => 'RESPONSE',
                'recipient' => ['id' => $recipientPsid],
                'message' => ['text' => $chunk],
            ];

            try {
                $response = Http::timeout(15)
                    ->withToken($accessToken)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->post($url, $payload);

                if (!$response->successful()) {
                    $error = $response->json()['error']['message'] ?? $response->body();
                    Log::error("Messenger sendDirectMessage failed for company {$company->id}: {$error}", [
                        'status' => $response->status(),
                        'recipient' => $recipientPsid,
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
                Log::error("Messenger sendDirectMessage exception for company {$company->id}: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info("Messenger DM sent successfully to {$recipientPsid} for company {$company->id}", [
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
     * Send a private Messenger reply triggered by a user's comment on a Facebook Page post.
     */
    public function sendPrivateReplyToComment(Company $company, string $commentId, string $dmText): array
    {
        $pageId = trim((string) $company->messenger_page_id);
        $accessToken = trim((string) $company->messenger_access_token);

        if ($pageId === '' || $accessToken === '') {
            return ['success' => false, 'error' => 'Missing Messenger credentials'];
        }

        $url = $this->graphBase() . "/{$pageId}/messages";
        $payload = [
            'recipient' => ['comment_id' => $commentId],
            'message' => ['text' => mb_substr(trim($dmText), 0, 1900)],
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
            Log::error("Messenger private reply to comment failed for company {$company->id}: {$error}");
            return ['success' => false, 'error' => $error];
        } catch (\Throwable $e) {
            Log::error("Messenger private reply exception for company {$company->id}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Post a public reply to a Facebook Page comment.
     */
    public function replyToComment(Company $company, string $commentId, string $publicReplyText): array
    {
        $accessToken = trim((string) $company->messenger_access_token);
        if ($accessToken === '' || trim($publicReplyText) === '') {
            return ['success' => false, 'error' => 'Missing token or reply text'];
        }

        $url = $this->graphBase() . "/{$commentId}/comments";

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($url, ['message' => trim($publicReplyText)]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            $error = $response->json()['error']['message'] ?? $response->body();
            Log::warning("Facebook public comment reply failed for company {$company->id}: {$error}");
            return ['success' => false, 'error' => $error];
        } catch (\Throwable $e) {
            Log::warning("Facebook public comment reply exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify Page Access Token against Facebook Graph API and return page metadata.
     */
    public function verifyConnection(string $pageId, string $accessToken): array
    {
        $pageId = trim($pageId);
        $accessToken = trim($accessToken);

        if ($accessToken === '') {
            return [
                'success' => false,
                'error' => 'Access Token is required',
            ];
        }

        $node = $pageId !== '' ? $pageId : 'me';

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->graphBase() . "/{$node}", [
                    'fields' => 'id,name,username,fan_count,link',
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            $error = $response->json()['error']['message'] ?? 'Failed to verify Facebook Page credentials';
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
     * Best-effort lookup of a Messenger sender's display name. Never throws.
     */
    public function getSenderProfile(Company $company, string $senderPsid): ?array
    {
        $accessToken = trim((string) $company->messenger_access_token);
        if ($accessToken === '' || $senderPsid === '') {
            return null;
        }

        try {
            $response = Http::timeout(3)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->graphBase() . "/{$senderPsid}", ['fields' => 'first_name,last_name,name']);

            if ($response->successful()) {
                $data = $response->json();
                $fullName = $data['name'] ?? trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
                return [
                    'name' => $fullName !== '' ? $fullName : null,
                ];
            }
        } catch (\Throwable $e) {
            // Best-effort only
        }

        return null;
    }

    /**
     * Check whether 1-click Facebook Messenger OAuth App credentials are configured on the server.
     */
    public function isOAuthConfigured(): bool
    {
        return !empty(env('META_MESSENGER_APP_ID')) && !empty(env('META_MESSENGER_APP_SECRET'));
    }

    /**
     * Build the official Facebook Login OAuth URL for 1-click Page Messenger connection.
     */
    public function buildOAuthUrl(Company $company): ?string
    {
        $appId = env('META_MESSENGER_APP_ID');
        if (empty($appId)) {
            return null;
        }

        $redirectUri = url('/company/messenger/oauth/callback');
        $state = Crypt::encryptString(json_encode([
            'company_id' => $company->id,
            'ts' => now()->timestamp,
        ]));

        $query = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'pages_show_list,pages_messaging,pages_read_engagement,pages_manage_metadata',
            'state' => $state,
        ]);

        return "https://www.facebook.com/{$this->apiVersion}/dialog/oauth?{$query}";
    }

    /**
     * Exchange OAuth code for a long-lived user token, retrieve the managed Page Access Token,
     * and subscribe the Page to Messenger & Feed webhooks.
     */
    public function exchangeOAuthCode(string $code): array
    {
        $appId = env('META_MESSENGER_APP_ID');
        $appSecret = env('META_MESSENGER_APP_SECRET');

        if (empty($appId) || empty($appSecret)) {
            return ['success' => false, 'error' => 'Messenger App ID / Secret not configured on server'];
        }

        $redirectUri = url('/company/messenger/oauth/callback');

        try {
            // 1. Exchange authorization code for short-lived user access token
            $tokenResp = Http::timeout(15)->get($this->graphBase() . '/oauth/access_token', [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

            if (!$tokenResp->successful()) {
                return [
                    'success' => false,
                    'error' => $tokenResp->json()['error']['message'] ?? $tokenResp->body(),
                ];
            }

            $shortToken = $tokenResp->json()['access_token'] ?? null;
            if (!$shortToken) {
                return ['success' => false, 'error' => 'No access token returned by Facebook'];
            }

            // 2. Upgrade to long-lived user token (Page tokens derived from this do not expire)
            $longToken = $shortToken;
            $longResp = Http::timeout(15)->get($this->graphBase() . '/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $shortToken,
            ]);

            if ($longResp->successful() && !empty($longResp->json()['access_token'])) {
                $longToken = $longResp->json()['access_token'];
            }

            // 3. Fetch managed Facebook Pages and pick the first Page Access Token
            $pagesResp = Http::timeout(15)
                ->withToken($longToken)
                ->get($this->graphBase() . '/me/accounts', [
                    'fields' => 'id,name,access_token,fan_count',
                ]);

            if (!$pagesResp->successful()) {
                return [
                    'success' => false,
                    'error' => $pagesResp->json()['error']['message'] ?? 'Failed to retrieve managed Facebook Pages',
                ];
            }

            $pages = $pagesResp->json()['data'] ?? [];
            if (empty($pages)) {
                return [
                    'success' => false,
                    'error' => 'لا توجد صفحات فيسبوك مُدارة على هذا الحساب.',
                ];
            }

            $page = $pages[0];
            $pageId = (string) ($page['id'] ?? '');
            $pageToken = (string) ($page['access_token'] ?? '');

            if ($pageId === '' || $pageToken === '') {
                return [
                    'success' => false,
                    'error' => 'تعذر الحصول على رمز وصول الصفحة (Page Access Token).',
                ];
            }

            // 4. Best-effort subscribe the Page to app webhooks
            try {
                Http::timeout(10)
                    ->withToken($pageToken)
                    ->post($this->graphBase() . "/{$pageId}/subscribed_apps", [
                        'subscribed_fields' => 'messages,messaging_postbacks,feed',
                    ]);
            } catch (\Throwable $e) {
                Log::warning("Messenger subscribed_apps best-effort call failed for page {$pageId}: " . $e->getMessage());
            }

            return [
                'success' => true,
                'page_id' => $pageId,
                'page_name' => $page['name'] ?? null,
                'access_token' => $pageToken,
                'fan_count' => isset($page['fan_count']) ? (int) $page['fan_count'] : null,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Split text cleanly into chunks within Messenger's 2000-char limit.
     */
    protected function splitMessage(string $text, int $maxLen = 1900): array
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
