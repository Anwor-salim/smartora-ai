<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    /**
     * Load Service Account credentials array from environment or storage file.
     */
    protected function loadCredentials(): ?array
    {
        $rawJson = $this->resolveSetting('GOOGLE_SERVICE_ACCOUNT_JSON');
        if ($rawJson !== null && $rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (!is_array($decoded)) {
                $b64Decoded = base64_decode($rawJson, true);
                if ($b64Decoded !== false) {
                    $decoded = json_decode($b64Decoded, true);
                }
            }
            if (is_array($decoded) && !empty($decoded['client_email']) && !empty($decoded['private_key'])) {
                return $decoded;
            }
        }

        $path = $this->resolveSetting('GOOGLE_SERVICE_ACCOUNT_PATH') ?: storage_path('app/google-service-account.json');
        if ($path && is_file($path) && is_readable($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded) && !empty($decoded['client_email']) && !empty($decoded['private_key'])) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Resolve an env setting honoring explicit getenv() overrides (including empty string in tests).
     */
    protected function resolveSetting(string $name): ?string
    {
        $envRaw = getenv($name);
        if ($envRaw !== false) {
            return trim((string) $envRaw);
        }

        $laravelEnv = env($name);
        return $laravelEnv !== null ? trim((string) $laravelEnv) : null;
    }

    public function isConfigured(): bool
    {
        return $this->loadCredentials() !== null;
    }

    public function getServiceAccountEmail(): ?string
    {
        return $this->loadCredentials()['client_email'] ?? null;
    }

    /**
     * Extract a Google Spreadsheet ID from a full Google Sheets URL or return a direct ID.
     */
    public function extractSpreadsheetId(?string $urlOrId): ?string
    {
        $value = trim((string) $urlOrId);
        if ($value === '') {
            return null;
        }

        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $value, $m)) {
            return $m[1];
        }

        if (preg_match('/^[a-zA-Z0-9_-]{15,}$/', $value)) {
            return $value;
        }

        return null;
    }

    /**
     * Obtain a cached OAuth2 access token using RS256 Service Account JWT assertion.
     */
    public function getAccessToken(): ?string
    {
        $creds = $this->loadCredentials();
        if (!$creds) {
            return null;
        }

        $cacheKey = 'google_sheets_token_' . md5((string) $creds['client_email']);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64UrlEncode((string) json_encode([
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signingInput = "{$header}.{$claims}";
        $signature = '';
        $privateKey = openssl_pkey_get_private((string) $creds['private_key']);
        if (!$privateKey) {
            Log::error('GoogleSheetsService: invalid RSA private key in service account credentials.');
            return null;
        }

        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            Log::error('GoogleSheetsService: failed to sign JWT assertion.');
            return null;
        }

        $jwt = $signingInput . '.' . $this->base64UrlEncode($signature);

        try {
            $tokenUrl = $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $response = Http::asForm()
                ->timeout(10)
                ->post($tokenUrl, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if ($response->successful()) {
                $token = (string) ($response->json('access_token') ?? '');
                if ($token !== '') {
                    Cache::put($cacheKey, $token, now()->addMinutes(50));
                    return $token;
                }
            } else {
                Log::warning('GoogleSheetsService OAuth2 failed: HTTP ' . $response->status() . ' ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('GoogleSheetsService OAuth2 exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Read row 1 headers from the target Google Sheet.
     */
    public function readHeaderRow(string $spreadsheetId, string $sheetTab = ''): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $range = $this->buildRange($sheetTab, 'A1:Z1');
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($range);

        try {
            $response = Http::withToken($token)->timeout(10)->get($url);
            if ($response->successful()) {
                $values = $response->json('values.0') ?? [];
                return array_values(array_filter(
                    array_map(fn ($v) => trim((string) $v), is_array($values) ? $values : []),
                    fn ($v) => $v !== ''
                ));
            }
            Log::warning('GoogleSheetsService readHeaderRow failed: HTTP ' . $response->status() . ' ' . $response->body());
        } catch (\Throwable $e) {
            Log::warning('GoogleSheetsService readHeaderRow exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Read all rows (A1:Z2000) returning normalized headers and 1-based row-number map.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>}|null
     */
    public function readAllRows(string $spreadsheetId, string $sheetTab = ''): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $range = $this->buildRange($sheetTab, 'A1:Z2000');
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($range);

        try {
            $response = Http::withToken($token)->timeout(12)->get($url);
            if (!$response->successful()) {
                Log::warning('GoogleSheetsService readAllRows failed: HTTP ' . $response->status());
                return null;
            }

            $allValues = $response->json('values') ?? [];
            if (!is_array($allValues) || empty($allValues)) {
                return ['headers' => [], 'rows' => []];
            }

            $rawHeaders = $allValues[0] ?? [];
            $headers = array_map(fn ($v) => trim((string) $v), is_array($rawHeaders) ? $rawHeaders : []);

            $rows = [];
            foreach ($allValues as $idx => $cells) {
                if ($idx === 0 || !is_array($cells)) {
                    continue;
                }
                $rowNumber = $idx + 1;
                $rows[$rowNumber] = array_map(fn ($v) => trim((string) $v), $cells);
            }

            return [
                'headers' => $headers,
                'rows' => $rows,
            ];
        } catch (\Throwable $e) {
            Log::warning('GoogleSheetsService readAllRows exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Append a row to Google Sheets and return the created 1-based row number if available.
     */
    public function appendRow(string $spreadsheetId, array $values, string $sheetTab = ''): ?int
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $range = $this->buildRange($sheetTab, 'A1');
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($range)
            . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post($url, [
                    'majorDimension' => 'ROWS',
                    'values' => [array_values($values)],
                ]);

            if ($response->successful()) {
                $updatedRange = (string) ($response->json('updates.updatedRange') ?? '');
                if (preg_match('/![A-Z]+(\d+)/i', $updatedRange, $m)) {
                    return (int) $m[1];
                }
                return 0;
            }

            Log::warning('GoogleSheetsService appendRow failed: HTTP ' . $response->status() . ' ' . $response->body());
        } catch (\Throwable $e) {
            Log::warning('GoogleSheetsService appendRow exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Update a single cell by 1-based row number and 0-based column index.
     */
    public function updateCell(
        string $spreadsheetId,
        int $rowNumber,
        int $columnIndex0Based,
        string $value,
        string $sheetTab = ''
    ): bool {
        $token = $this->getAccessToken();
        if (!$token || $rowNumber < 1 || $columnIndex0Based < 0) {
            return false;
        }

        $colLetter = $this->columnIndexToLetter($columnIndex0Based);
        $range = $this->buildRange($sheetTab, "{$colLetter}{$rowNumber}");
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($range)
            . '?valueInputOption=USER_ENTERED';

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->put($url, [
                    'range' => $range,
                    'majorDimension' => 'ROWS',
                    'values' => [[$value]],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('GoogleSheetsService updateCell failed: HTTP ' . $response->status() . ' ' . $response->body());
        } catch (\Throwable $e) {
            Log::warning('GoogleSheetsService updateCell exception: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Convert a 0-based column index to Excel/Sheets column letter (0 => A, 25 => Z, 26 => AA).
     */
    public function columnIndexToLetter(int $index): string
    {
        $letter = '';
        $num = $index;
        while ($num >= 0) {
            $letter = chr(($num % 26) + 65) . $letter;
            $num = intdiv($num, 26) - 1;
        }

        return $letter;
    }

    /**
     * Classify Sheet headers into system-managed columns vs dynamic customer-collected fields.
     *
     * @return array{
     *   all: array<int, string>,
     *   system_map: array<string, int>,
     *   customer_fields: array<int, array{label: string, type: string, index: int}>
     * }
     */
    public function classifyHeaders(array $headers): array
    {
        $systemMap = [];
        $customerFields = [];

        foreach ($headers as $index => $rawHeader) {
            $label = trim((string) $rawHeader);
            if ($label === '') {
                continue;
            }

            $norm = $this->normalizeHeader($label);
            $sysKey = $this->detectSystemHeaderKey($norm);

            if ($sysKey !== null) {
                if (!isset($systemMap[$sysKey])) {
                    $systemMap[$sysKey] = (int) $index;
                }
                continue;
            }

            $customerFields[] = [
                'label' => $label,
                'type' => $this->detectFieldType($norm),
                'index' => (int) $index,
            ];
        }

        return [
            'all' => array_values($headers),
            'system_map' => $systemMap,
            'customer_fields' => $customerFields,
        ];
    }

    protected function detectSystemHeaderKey(string $norm): ?string
    {
        if (preg_match('/^(رقم الحجز|رقم الطلب|كود الحجز|الكود|booking code|code|id)$/u', $norm)) {
            return 'booking_code';
        }
        if (preg_match('/^(رقم الدور|الدور|الترتيب|queue|queue number)$/u', $norm)) {
            return 'queue_number';
        }
        if (preg_match('/^(رقم الجوال|الجوال|الهاتف|رقم الهاتف|رقم الواتساب|الواتساب|واتساب|phone|whatsapp|mobile)$/u', $norm)) {
            return 'customer_phone';
        }
        if (preg_match('/^(الحاله|حاله الحجز|حاله الطلب|status|state)$/u', $norm)) {
            return 'status';
        }
        if (preg_match('/^(تاريخ التسجيل|وقت التسجيل|تاريخ الانشاء|created at|timestamp)$/u', $norm)) {
            return 'created_at';
        }
        if (preg_match('/^(ملاحظات الاداره|ملاحظات الاستقبال|سبب الرفض|admin notes|rejection reason)$/u', $norm)) {
            return 'admin_notes';
        }

        return null;
    }

    protected function detectFieldType(string $norm): string
    {
        if (preg_match('/(تاريخ|يوم|اليوم|التاريخ|date|day)/u', $norm)) {
            return 'date';
        }
        if (preg_match('/(وقت|الوقت|ساعه|الساعه|time|hour)/u', $norm)) {
            return 'time';
        }
        if (preg_match('/(اسم|الاسم|المريض|العميل|name)/u', $norm)) {
            return 'name';
        }
        if (preg_match('/(عمر|العمر|سن|السن|عدد|age|count)/u', $norm)) {
            return 'number';
        }

        return 'text';
    }

    protected function normalizeHeader(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[إأآا]/u', 'ا', $text) ?? $text;
        $text = preg_replace('/[ةه]/u', 'ه', $text) ?? $text;
        $text = preg_replace('/[يى]/u', 'ي', $text) ?? $text;
        $text = preg_replace('/[\x{064B}-\x{0652}]/u', '', $text) ?? $text;
        $text = preg_replace('/[_\-]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    protected function buildRange(string $sheetTab, string $cellRange): string
    {
        $tab = trim($sheetTab);
        if ($tab === '') {
            return $cellRange;
        }

        $escaped = str_replace("'", "''", $tab);
        return "'{$escaped}'!{$cellRange}";
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
