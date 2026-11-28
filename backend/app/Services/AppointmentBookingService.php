<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\SystemNotification;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppointmentBookingService
{
    public function __construct(
        protected GoogleSheetsService $sheetsService,
        protected ChannelMessageService $channelService,
    ) {}

    public function getCompanyTimezone(Company $company): string
    {
        $tz = (string) ($company->settings['timezone'] ?? 'Asia/Riyadh');
        return in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : 'Asia/Riyadh';
    }

    /**
     * Refresh dynamic column headers from the company's Google Sheet (or fallback custom fields).
     *
     * @return array{
     *   all: array<int, string>,
     *   system_map: array<string, int>,
     *   customer_fields: array<int, array{label: string, type: string, index: int}>,
     *   source: string
     * }
     */
    public function refreshSheetHeaders(Company $company, bool $force = false): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $sheetId = $this->sheetsService->extractSpreadsheetId(
            $settings['booking_sheet_id'] ?? ($settings['booking_sheet_url'] ?? '')
        );
        $sheetTab = trim((string) ($settings['booking_sheet_tab'] ?? ''));
        $source = 'fallback';

        if ($sheetId && $this->sheetsService->isConfigured()) {
            $syncedAtStr = $settings['booking_sheet_headers_synced_at'] ?? null;
            $isStale = $force || empty($settings['booking_sheet_headers']) || empty($syncedAtStr);
            if (!$isStale && is_string($syncedAtStr)) {
                try {
                    $isStale = Carbon::parse($syncedAtStr)->lt(Carbon::now()->subMinutes(5));
                } catch (\Throwable) {
                    $isStale = true;
                }
            }

            if ($isStale) {
                $liveHeaders = $this->sheetsService->readHeaderRow($sheetId, $sheetTab);
                if (is_array($liveHeaders) && !empty($liveHeaders)) {
                    $settings['booking_sheet_id'] = $sheetId;
                    $settings['booking_sheet_headers'] = $liveHeaders;
                    $settings['booking_sheet_headers_synced_at'] = Carbon::now()->toIso8601String();
                    $company->update(['settings' => $settings]);
                    $source = 'google_sheets';
                }
            } else {
                $source = 'google_sheets_cached';
            }
        }

        if (!empty($settings['booking_sheet_headers']) && is_array($settings['booking_sheet_headers'])) {
            $classified = $this->sheetsService->classifyHeaders($settings['booking_sheet_headers']);
            $classified['source'] = $source;
            return $classified;
        }

        // Fallback to custom fields configured in Company Booking Settings
        $rawCustom = $settings['booking_custom_fields'] ?? ['الاسم', 'التاريخ', 'الوقت'];
        if (is_string($rawCustom)) {
            $customFields = array_values(array_filter(array_map('trim', preg_split('/[,،\n]+/u', $rawCustom) ?: [])));
        } elseif (is_array($rawCustom)) {
            $customFields = array_values(array_filter(array_map('trim', $rawCustom)));
        } else {
            $customFields = ['الاسم', 'التاريخ', 'الوقت'];
        }

        if (empty($customFields)) {
            $customFields = ['الاسم', 'التاريخ', 'الوقت'];
        }

        $fallbackHeaders = array_merge(
            ['رقم الحجز', 'رقم الدور', 'رقم الواتساب'],
            $customFields,
            ['الحالة', 'تاريخ التسجيل']
        );

        $classified = $this->sheetsService->classifyHeaders($fallbackHeaders);
        $classified['source'] = 'custom_fields';
        return $classified;
    }

    public function findActiveAppointment(Company $company, string $customerPhone): ?Appointment
    {
        $today = Carbon::now($this->getCompanyTimezone($company))->toDateString();

        return Appointment::where('company_id', $company->id)
            ->where('customer_phone', $customerPhone)
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->whereDate('appointment_date', '>=', $today)
            ->orderBy('appointment_date')
            ->first();
    }

    public function getWorkingDays(Company $company): array
    {
        $days = $company->settings['booking_working_days'] ?? null;
        if (is_array($days) && !empty($days)) {
            return array_values($days);
        }

        return ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'];
    }

    public function isWorkingDay(Company $company, Carbon $date): bool
    {
        $dateStr = $date->toDateString();
        $overrides = $company->settings['booking_date_overrides'] ?? [];
        if (is_array($overrides) && array_key_exists($dateStr, $overrides)) {
            return (int) $overrides[$dateStr] > 0;
        }

        $dayMap = [
            0 => 'sun',
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
        ];
        $dayCode = $dayMap[(int) $date->dayOfWeek] ?? 'sun';

        return in_array($dayCode, $this->getWorkingDays($company), true);
    }

    public function getDailyLimit(Company $company, Carbon $date): int
    {
        $dateStr = $date->toDateString();
        $overrides = $company->settings['booking_date_overrides'] ?? [];
        if (is_array($overrides) && array_key_exists($dateStr, $overrides)) {
            return max(0, (int) $overrides[$dateStr]);
        }

        return max(1, (int) ($company->settings['booking_daily_limit'] ?? 10));
    }

    public function countBookedForDate(Company $company, Carbon $date): int
    {
        return Appointment::where('company_id', $company->id)
            ->whereDate('appointment_date', $date->toDateString())
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->count();
    }

    public function getRemainingSlots(Company $company, Carbon $date): int
    {
        if (!$this->isWorkingDay($company, $date)) {
            return 0;
        }

        $limit = $this->getDailyLimit($company, $date);
        $booked = $this->countBookedForDate($company, $date);

        return max(0, $limit - $booked);
    }

    /**
     * Find the nearest upcoming working day with available booking slots.
     *
     * @return array{
     *   date: Carbon,
     *   date_string: string,
     *   arabic_day: string,
     *   remaining: int,
     *   limit: int,
     *   skipped_days: array<int, array{date: string, day: string, reason: string}>
     * }|null
     */
    public function findNextAvailableDate(Company $company, Carbon $fromDate, int $maxLookaheadDays = 30): ?array
    {
        $skipped = [];
        $cursor = $fromDate->copy()->startOfDay();

        for ($i = 0; $i < $maxLookaheadDays; $i++) {
            $isWorking = $this->isWorkingDay($company, $cursor);
            $remaining = $this->getRemainingSlots($company, $cursor);

            if ($isWorking && $remaining > 0) {
                return [
                    'date' => $cursor->copy(),
                    'date_string' => $cursor->toDateString(),
                    'arabic_day' => $this->arabicDayName($cursor),
                    'remaining' => $remaining,
                    'limit' => $this->getDailyLimit($company, $cursor),
                    'skipped_days' => $skipped,
                ];
            }

            $skipped[] = [
                'date' => $cursor->toDateString(),
                'day' => $this->arabicDayName($cursor),
                'reason' => $isWorking ? 'full' : 'closed',
            ];
            $cursor->addDay();
        }

        return null;
    }

    public function arabicDayName(Carbon $date): string
    {
        return match ((int) $date->dayOfWeek) {
            0 => 'الأحد',
            1 => 'الإثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
            default => '',
        };
    }

    /**
     * Parse an Arabic or numeric date expression into a Carbon date in the company's timezone.
     */
    public function parseArabicDate(string $text, string $timezone = 'Asia/Riyadh'): ?Carbon
    {
        $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $clean = str_replace($eastern, $western, trim($text));
        $now = Carbon::now($timezone)->startOfDay();

        // 1. Explicit YYYY-MM-DD or YYYY/MM/DD
        if (preg_match('/\b(20\d{2})[-\/](\d{1,2})[-\/](\d{1,2})\b/', $clean, $m)) {
            try {
                return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3], 0, 0, 0, $timezone)->startOfDay();
            } catch (\Throwable) {
            }
        }

        // 2. Explicit DD/MM/YYYY, DD-MM-YYYY, or unambiguous MM/DD/YYYY, MM-DD-YYYY
        if (preg_match('/\b(\d{1,2})[-\/](\d{1,2})[-\/](20\d{2})\b/', $clean, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            $year = (int) $m[3];
            $day = 0;
            $month = 0;

            if ($first >= 1 && $first <= 31 && $second >= 1 && $second <= 12) {
                $day = $first;
                $month = $second;
            } elseif ($first >= 1 && $first <= 12 && $second >= 13 && $second <= 31) {
                $month = $first;
                $day = $second;
            }

            if ($day >= 1 && $month >= 1 && checkdate($month, $day, $year)) {
                try {
                    return Carbon::create($year, $month, $day, 0, 0, 0, $timezone)->startOfDay();
                } catch (\Throwable) {
                }
            }
        }

        // 3. Explicit DD/MM, DD-MM, or unambiguous MM/DD, MM-DD (e.g. "11-27")
        if (preg_match('/\b(\d{1,2})[-\/](\d{1,2})\b/', $clean, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            $day = 0;
            $month = 0;

            if ($first >= 1 && $first <= 31 && $second >= 1 && $second <= 12) {
                $day = $first;
                $month = $second;
            } elseif ($first >= 1 && $first <= 12 && $second >= 13 && $second <= 31) {
                $month = $first;
                $day = $second;
            }

            if ($day >= 1 && $month >= 1) {
                $year = $now->year;
                if (!checkdate($month, $day, $year) && checkdate($month, $day, $year + 1)) {
                    $year++;
                }
                if (checkdate($month, $day, $year)) {
                    try {
                        $candidate = Carbon::create($year, $month, $day, 0, 0, 0, $timezone)->startOfDay();
                        if ($candidate->lt($now->copy()->subDays(60))) {
                            $candidate->addYear();
                        }
                        return $candidate;
                    } catch (\Throwable) {
                    }
                }
            }
        }

        $norm = $this->normalizeArabicText($clean);

        // 4. Explicit Arabic/English month-name dates (e.g., "28 نوفمبر", "28 نوفمبر 2026", "28 Nov")
        $monthMap = [
            'يناير' => 1, 'جانفي' => 1, 'january' => 1, 'jan' => 1,
            'فبراير' => 2, 'فيفري' => 2, 'february' => 2, 'feb' => 2,
            'مارس' => 3, 'march' => 3, 'mar' => 3,
            'ابريل' => 4, 'افريل' => 4, 'april' => 4, 'apr' => 4,
            'مايو' => 5, 'ماي' => 5, 'may' => 5,
            'يونيو' => 6, 'جوان' => 6, 'june' => 6, 'jun' => 6,
            'يوليو' => 7, 'جويلية' => 7, 'جويليه' => 7, 'july' => 7, 'jul' => 7,
            'اغسطس' => 8, 'اوت' => 8, 'august' => 8, 'aug' => 8,
            'سبتمبر' => 9, 'september' => 9, 'sept' => 9, 'sep' => 9,
            'اكتوبر' => 10, 'october' => 10, 'oct' => 10,
            'نوفمبر' => 11, 'november' => 11, 'nov' => 11,
            'ديسمبر' => 12, 'december' => 12, 'dec' => 12,
        ];
        $monthPattern = implode('|', array_keys($monthMap));
        if (preg_match('/\b(\d{1,2})\s+(?:من\s+)?(' . $monthPattern . ')(?:\s+(20\d{2}))?\b/ui', $norm, $mm)) {
            $day = (int) $mm[1];
            $month = $monthMap[$mm[2]] ?? 0;
            $hasExplicitYear = !empty($mm[3]);
            $year = $hasExplicitYear ? (int) $mm[3] : $now->year;
            if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && checkdate($month, $day, $year)) {
                try {
                    $candidate = Carbon::create($year, $month, $day, 0, 0, 0, $timezone)->startOfDay();
                    if (!$hasExplicitYear && $candidate->lt($now->copy()->subDays(60))) {
                        $candidate->addYear();
                    }
                    return $candidate;
                } catch (\Throwable) {
                }
            }
        }

        // 5. Explicit day-of-month ONLY when preceded by تاريخ / بتاريخ / يوم (never bare numbers like age 28)
        if (preg_match('/(?:بتاريخ|تاريخ|يوم)\s*[:\-]?\s*(\d{1,2})(?!\s*[\/\-]\s*\d)(?!\s*(?:سنه|عام|ساعه|صباح|مساء|عصر|ظهر|ليل))/u', $norm, $dm)) {
            $day = (int) $dm[1];
            if ($day >= 1 && $day <= 31) {
                if (checkdate($now->month, $day, $now->year)) {
                    $candidate = Carbon::create($now->year, $now->month, $day, 0, 0, 0, $timezone)->startOfDay();
                    if ($candidate->gte($now)) {
                        return $candidate;
                    }
                }
                $nextMonth = $now->copy()->startOfMonth()->addMonth();
                if (checkdate($nextMonth->month, $day, $nextMonth->year)) {
                    return Carbon::create($nextMonth->year, $nextMonth->month, $day, 0, 0, 0, $timezone)->startOfDay();
                }
            }
        }

        // 6. Relative Arabic date terms
        if (preg_match('/(بعد بكره|بعد بكرا|بعد غد)/u', $norm)) {
            return $now->copy()->addDays(2);
        }
        if (preg_match('/(بكره|بكرا|غدا|tomorrow)/u', $norm)) {
            return $now->copy()->addDay();
        }
        if (preg_match('/\b(اليوم|today)\b/u', $norm)) {
            return $now->copy();
        }

        // 5. Arabic / English weekday names
        $weekdays = [
            0 => '/(الاحد|احد|\bsunday\b)/u',
            1 => '/(الاثنين|اثنين|\bmonday\b)/u',
            2 => '/(الثلاثاء|الثلاثا|ثلاثاء|\btuesday\b)/u',
            3 => '/(الاربعاء|الاربعا|اربعاء|\bwednesday\b)/u',
            4 => '/(الخميس|خميس|\bthursday\b)/u',
            5 => '/(الجمعه|جمعه|\bfriday\b)/u',
            6 => '/(السبت|سبت|\bsaturday\b)/u',
        ];

        foreach ($weekdays as $targetDow => $pattern) {
            if (preg_match($pattern, $norm)) {
                $daysAhead = ($targetDow - (int) $now->dayOfWeek + 7) % 7;
                if ($daysAhead === 0 && preg_match('/(الجاي|القادم|المقبل|next)/u', $norm)) {
                    $daysAhead = 7;
                }
                return $now->copy()->addDays($daysAhead);
            }
        }

        return null;
    }

    /**
     * Atomically create an appointment (enforcing duplicate prevention and daily capacity),
     * record notification, sync row to Google Sheet, and optionally alert reception.
     *
     * @return array{
     *   ok: bool,
     *   reason?: string,
     *   existing?: Appointment,
     *   requested_date?: Carbon,
     *   next_available?: array|null,
     *   appointment?: Appointment,
     *   sheet_synced?: bool
     * }
     */
    public function createAppointment(
        Company $company,
        Conversation $conversation,
        array $formData,
        Carbon $date,
        ?string $time = null,
        ?string $customerName = null
    ): array {
        $tz = $this->getCompanyTimezone($company);
        $targetDate = $date->copy()->setTimezone($tz)->startOfDay();
        $dateString = $targetDate->toDateString();

        $txResult = DB::transaction(function () use ($company, $conversation, $formData, $targetDate, $dateString, $time, $customerName) {
            $existing = $this->findActiveAppointment($company, $conversation->customer_phone);
            if ($existing) {
                return [
                    'ok' => false,
                    'reason' => 'duplicate',
                    'existing' => $existing,
                ];
            }

            if ($this->getRemainingSlots($company, $targetDate) <= 0) {
                return [
                    'ok' => false,
                    'reason' => 'day_full',
                    'requested_date' => $targetDate,
                    'next_available' => $this->findNextAvailableDate($company, $targetDate->copy()->addDay()),
                ];
            }

            $maxQueue = (int) Appointment::where('company_id', $company->id)
                ->whereDate('appointment_date', $dateString)
                ->max('queue_number');
            $queueNumber = $maxQueue + 1;

            $bookingCode = 'BK-' . $company->id . '-' . $targetDate->format('md') . '-' . str_pad((string) $queueNumber, 2, '0', STR_PAD_LEFT);
            if (Appointment::where('booking_code', $bookingCode)->exists()) {
                $bookingCode .= '-' . strtoupper(Str::random(3));
            }

            $approvalMode = (string) ($company->settings['booking_approval_mode'] ?? 'reception_approval');
            $initialStatus = $approvalMode === 'auto_confirm' ? 'approved' : 'pending';

            $resolvedName = $customerName ?: $this->extractNameFromFormData($formData) ?: $conversation->customer_name;
            if ($resolvedName && $resolvedName !== $conversation->customer_name && str_starts_with((string) $conversation->customer_name, 'عميل ')) {
                $conversation->update(['customer_name' => $resolvedName]);
            }

            $appointment = Appointment::create([
                'company_id' => $company->id,
                'conversation_id' => $conversation->id,
                'booking_code' => $bookingCode,
                'channel' => $conversation->channel ?: 'whatsapp',
                'customer_phone' => $conversation->customer_phone,
                'customer_name' => $resolvedName,
                'appointment_date' => $dateString,
                'appointment_time' => $time,
                'queue_number' => $queueNumber,
                'form_data' => $formData,
                'status' => $initialStatus,
                'approved_at' => $initialStatus === 'approved' ? Carbon::now() : null,
                'notified_status' => $initialStatus === 'approved' ? 'approved' : null,
                'customer_notified_at' => $initialStatus === 'approved' ? Carbon::now() : null,
            ]);

            $dayName = $this->arabicDayName($targetDate);
            $notifTitle = $initialStatus === 'approved'
                ? "حجز مؤكد جديد ({$bookingCode})"
                : "طلب حجز جديد بانتظار المراجعة ({$bookingCode})";
            $notifMsg = "العميل: {$resolvedName} ({$conversation->customer_phone}) | الموعد: {$dayName} {$dateString}"
                . ($time ? " الساعة {$time}" : '')
                . " | الدور #{$queueNumber}";

            SystemNotification::create([
                'company_id' => $company->id,
                'title' => $notifTitle,
                'message' => $notifMsg,
                'type' => 'new_appointment',
                'data' => [
                    'appointment_id' => $appointment->id,
                    'booking_code' => $bookingCode,
                    'date' => $dateString,
                    'queue_number' => $queueNumber,
                    'status' => $initialStatus,
                ],
            ]);

            return [
                'ok' => true,
                'appointment' => $appointment,
            ];
        });

        if (!$txResult['ok']) {
            return $txResult;
        }

        /** @var Appointment $appointment */
        $appointment = $txResult['appointment'];
        $sheetSynced = $this->syncAppointmentToSheet($appointment);

        $dayName = $this->arabicDayName($targetDate);
        $receptionText = "📋 *طلب حجز جديد في {$company->name}*\n"
            . "• الكود: *{$appointment->booking_code}*\n"
            . "• العميل: {$appointment->customer_name} ({$appointment->customer_phone})\n"
            . "• الموعد: {$dayName} {$dateString}" . ($time ? " — {$time}" : '') . "\n"
            . "• الدور: *#{$appointment->queue_number}*\n"
            . "• الحالة: {$appointment->statusLabelArabic()}";

        $this->channelService->notifyReception($company, $receptionText);

        return [
            'ok' => true,
            'appointment' => $appointment->fresh(),
            'sheet_synced' => $sheetSynced,
        ];
    }

    /**
     * Append a newly created appointment row to the company's configured Google Sheet.
     */
    public function syncAppointmentToSheet(Appointment $appointment): bool
    {
        $company = $appointment->company;
        if (!$company) {
            return false;
        }

        $settings = is_array($company->settings) ? $company->settings : [];
        $sheetId = $this->sheetsService->extractSpreadsheetId(
            $settings['booking_sheet_id'] ?? ($settings['booking_sheet_url'] ?? '')
        );
        if (!$sheetId || !$this->sheetsService->isConfigured()) {
            return false;
        }

        $sheetTab = trim((string) ($settings['booking_sheet_tab'] ?? ''));
        $schema = $this->refreshSheetHeaders($company);
        $headers = $schema['all'] ?? [];
        if (empty($headers)) {
            return false;
        }

        $rowValues = $this->buildSheetRowValues($appointment, $headers, $schema['system_map'] ?? []);
        $rowNumber = $this->sheetsService->appendRow($sheetId, $rowValues, $sheetTab);

        if ($rowNumber === null) {
            return false;
        }

        $updates = ['sheet_synced_at' => Carbon::now()];
        if ($rowNumber > 0) {
            $updates['sheet_row_number'] = $rowNumber;
        }
        $appointment->update($updates);

        return true;
    }

    /**
     * Approve a pending appointment, update Google Sheet status cell, and notify customer once.
     */
    public function approveAppointment(Appointment $appointment, bool $syncToSheet = true): bool
    {
        $statusChanged = $appointment->status !== 'approved';
        $alreadyNotified = $appointment->notified_status === 'approved';

        $appointment->update([
            'status' => 'approved',
            'approved_at' => $appointment->approved_at ?: Carbon::now(),
            'rejection_reason' => null,
        ]);

        if ($syncToSheet && $statusChanged) {
            $this->updateAppointmentStatusInSheet($appointment);
        }

        if (!$alreadyNotified) {
            $company = $appointment->company;
            $dateStr = $appointment->appointment_date?->toDateString() ?? '';
            $dayName = $appointment->appointment_date ? $this->arabicDayName($appointment->appointment_date) : '';

            $msg = "✅ *تم قبول وتأكيد موعدك في {$company->name}!*\n"
                . "• رقم الحجز: *{$appointment->booking_code}*\n"
                . "• اليوم والتاريخ: *{$dayName} ({$dateStr})*\n";
            if ($appointment->appointment_time) {
                $msg .= "• الوقت: *{$appointment->appointment_time}*\n";
            }
            $msg .= "• رقم الدور: *#{$appointment->queue_number}*\n";
            if ($company->address) {
                $msg .= "📍 الموقع: {$company->address}\n";
            }
            $msg .= "\nيسعدنا استقبالك ونتمنى لك دوام الصحة والعافية.";

            $this->channelService->sendToCustomer(
                $company,
                $appointment->customer_phone,
                $msg,
                $appointment->channel ?: 'whatsapp',
                $appointment->conversation
            );

            $appointment->update([
                'notified_status' => 'approved',
                'customer_notified_at' => Carbon::now(),
            ]);
        }

        return true;
    }

    /**
     * Reject an appointment, free the capacity slot, update Sheet, and notify customer once.
     */
    public function rejectAppointment(Appointment $appointment, ?string $reason = null, bool $syncToSheet = true): bool
    {
        $alreadyNotified = $appointment->notified_status === 'rejected';
        $cleanReason = trim((string) ($reason ?? ''));

        $appointment->update([
            'status' => 'rejected',
            'rejected_at' => $appointment->rejected_at ?: Carbon::now(),
            'rejection_reason' => $cleanReason !== '' ? $cleanReason : null,
        ]);

        if ($syncToSheet) {
            $this->updateAppointmentStatusInSheet($appointment);
        }

        if (!$alreadyNotified) {
            $company = $appointment->company;
            $dateStr = $appointment->appointment_date?->toDateString() ?? '';
            $next = $appointment->appointment_date
                ? $this->findNextAvailableDate($company, $appointment->appointment_date->copy()->addDay())
                : null;

            $msg = "نعتذر منك، لم يتم قبول طلب الحجز (*{$appointment->booking_code}*) بتاريخ *{$dateStr}* في *{$company->name}*.";
            if ($cleanReason !== '') {
                $msg .= "\n• السبب: {$cleanReason}";
            }
            if ($next) {
                $msg .= "\n\n💡 أقرب موعد متاح حالياً هو *{$next['arabic_day']} ({$next['date_string']})*. يمكنك إرسال كلمة (حجز) لتسجيل موعد جديد.";
            }

            $this->channelService->sendToCustomer(
                $company,
                $appointment->customer_phone,
                $msg,
                $appointment->channel ?: 'whatsapp',
                $appointment->conversation
            );

            $appointment->update([
                'notified_status' => 'rejected',
                'customer_notified_at' => Carbon::now(),
            ]);
        }

        return true;
    }

    /**
     * Cancel an appointment (by customer in chat or by reception) and update Google Sheet.
     */
    public function cancelAppointment(Appointment $appointment, string $cancelledBy = 'customer', bool $syncToSheet = true): bool
    {
        $alreadyNotified = $appointment->notified_status === 'cancelled';

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => $appointment->cancelled_at ?: Carbon::now(),
        ]);

        if ($syncToSheet) {
            $this->updateAppointmentStatusInSheet($appointment);
        }

        if ($cancelledBy !== 'customer' && !$alreadyNotified) {
            $company = $appointment->company;
            $dateStr = $appointment->appointment_date?->toDateString() ?? '';
            $msg = "تم إلغاء حجزك رقم *{$appointment->booking_code}* بتاريخ *{$dateStr}* لدى *{$company->name}*. إذا رغبت في حجز موعد آخر أرسل (حجز) في أي وقت.";

            $this->channelService->sendToCustomer(
                $company,
                $appointment->customer_phone,
                $msg,
                $appointment->channel ?: 'whatsapp',
                $appointment->conversation
            );

            $appointment->update([
                'notified_status' => 'cancelled',
                'customer_notified_at' => Carbon::now(),
            ]);
        }

        return true;
    }

    /**
     * Two-way sync: read the company's Google Sheet and process any status changes made by reception directly in the Sheet.
     *
     * @return array{headers_count: int, approved: int, rejected: int, cancelled: int, skipped: bool}
     */
    public function syncCompanySheetStatuses(Company $company): array
    {
        $stats = ['headers_count' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0, 'skipped' => false];
        $settings = is_array($company->settings) ? $company->settings : [];
        $sheetId = $this->sheetsService->extractSpreadsheetId(
            $settings['booking_sheet_id'] ?? ($settings['booking_sheet_url'] ?? '')
        );

        if (!$sheetId || !$this->sheetsService->isConfigured()) {
            $stats['skipped'] = true;
            return $stats;
        }

        $sheetTab = trim((string) ($settings['booking_sheet_tab'] ?? ''));
        $sheetData = $this->sheetsService->readAllRows($sheetId, $sheetTab);
        if (!$sheetData || empty($sheetData['headers'])) {
            $stats['skipped'] = true;
            return $stats;
        }

        $headers = $sheetData['headers'];
        $stats['headers_count'] = count($headers);

        $settings['booking_sheet_headers'] = $headers;
        $settings['booking_sheet_headers_synced_at'] = Carbon::now()->toIso8601String();
        $company->update(['settings' => $settings]);

        $classified = $this->sheetsService->classifyHeaders($headers);
        $systemMap = $classified['system_map'];

        if (!isset($systemMap['status'])) {
            return $stats;
        }

        $statusCol = $systemMap['status'];
        $codeCol = $systemMap['booking_code'] ?? null;
        $notesCol = $systemMap['admin_notes'] ?? null;

        foreach ($sheetData['rows'] as $rowNumber => $cells) {
            $rawStatus = trim((string) ($cells[$statusCol] ?? ''));
            if ($rawStatus === '') {
                continue;
            }

            $bookingCode = $codeCol !== null ? trim((string) ($cells[$codeCol] ?? '')) : '';
            $appointment = null;
            if ($bookingCode !== '') {
                $appointment = Appointment::where('company_id', $company->id)
                    ->where('booking_code', $bookingCode)
                    ->first();
            }
            if (!$appointment) {
                $appointment = Appointment::where('company_id', $company->id)
                    ->where('sheet_row_number', $rowNumber)
                    ->first();
            }
            if (!$appointment) {
                continue;
            }

            if (!$appointment->sheet_row_number) {
                $appointment->update(['sheet_row_number' => $rowNumber]);
            }

            $normStatus = $this->normalizeArabicText($rawStatus);
            if (preg_match('/^(مقبول|مؤكد|تم القبول|تم التاكيد|موافق|approved|confirmed|accepted)$/u', $normStatus)) {
                if ($appointment->status !== 'approved') {
                    $this->approveAppointment($appointment, syncToSheet: false);
                    $stats['approved']++;
                }
            } elseif (preg_match('/^(مرفوض|رفض|معتذر|rejected|declined)$/u', $normStatus)) {
                if ($appointment->status !== 'rejected') {
                    $reason = $notesCol !== null ? trim((string) ($cells[$notesCol] ?? '')) : null;
                    $this->rejectAppointment($appointment, $reason ?: null, syncToSheet: false);
                    $stats['rejected']++;
                }
            } elseif (preg_match('/^(ملغي|الغاء|تم الالغاء|cancelled|canceled)$/u', $normStatus)) {
                if ($appointment->status !== 'cancelled') {
                    $this->cancelAppointment($appointment, 'reception', syncToSheet: false);
                    $stats['cancelled']++;
                }
            }
        }

        return $stats;
    }

    protected function updateAppointmentStatusInSheet(Appointment $appointment): bool
    {
        $company = $appointment->company;
        if (!$company || !$appointment->sheet_row_number) {
            return false;
        }

        $settings = is_array($company->settings) ? $company->settings : [];
        $sheetId = $this->sheetsService->extractSpreadsheetId(
            $settings['booking_sheet_id'] ?? ($settings['booking_sheet_url'] ?? '')
        );
        if (!$sheetId || !$this->sheetsService->isConfigured()) {
            return false;
        }

        $sheetTab = trim((string) ($settings['booking_sheet_tab'] ?? ''));
        $schema = $this->refreshSheetHeaders($company);
        $systemMap = $schema['system_map'] ?? [];

        if (!isset($systemMap['status'])) {
            return false;
        }

        $updated = $this->sheetsService->updateCell(
            $sheetId,
            (int) $appointment->sheet_row_number,
            (int) $systemMap['status'],
            $appointment->statusLabelArabic(),
            $sheetTab
        );

        if ($updated && isset($systemMap['admin_notes']) && !empty($appointment->rejection_reason)) {
            $this->sheetsService->updateCell(
                $sheetId,
                (int) $appointment->sheet_row_number,
                (int) $systemMap['admin_notes'],
                (string) $appointment->rejection_reason,
                $sheetTab
            );
        }

        if ($updated) {
            $appointment->update(['sheet_synced_at' => Carbon::now()]);
        }

        return $updated;
    }

    protected function buildSheetRowValues(Appointment $appointment, array $headers, array $systemMap): array
    {
        $formData = is_array($appointment->form_data) ? $appointment->form_data : [];
        $indexToSysKey = array_flip($systemMap);
        $values = [];

        foreach ($headers as $idx => $header) {
            $headerLabel = trim((string) $header);
            if (isset($indexToSysKey[$idx])) {
                $sysKey = $indexToSysKey[$idx];
                $values[] = match ($sysKey) {
                    'booking_code' => $appointment->booking_code,
                    'queue_number' => (string) $appointment->queue_number,
                    'customer_phone' => $appointment->customer_phone,
                    'status' => $appointment->statusLabelArabic(),
                    'created_at' => $appointment->created_at?->format('Y-m-d H:i') ?? Carbon::now()->format('Y-m-d H:i'),
                    'admin_notes' => (string) ($appointment->rejection_reason ?? ''),
                    default => '',
                };
                continue;
            }

            if (array_key_exists($headerLabel, $formData)) {
                $values[] = (string) $formData[$headerLabel];
                continue;
            }

            // Fallback lookup by normalized label
            $normHeader = $this->normalizeArabicText($headerLabel);
            $matchedVal = null;
            foreach ($formData as $k => $v) {
                if ($this->normalizeArabicText((string) $k) === $normHeader) {
                    $matchedVal = (string) $v;
                    break;
                }
            }

            if ($matchedVal !== null) {
                $values[] = $matchedVal;
            } elseif (preg_match('/(اسم|الاسم|name)/u', $normHeader)) {
                $values[] = (string) ($appointment->customer_name ?? '');
            } elseif (preg_match('/(تاريخ|يوم|date|day)/u', $normHeader) && $appointment->appointment_date) {
                $values[] = $this->arabicDayName($appointment->appointment_date) . ' ' . $appointment->appointment_date->format('Y-m-d');
            } elseif (preg_match('/(وقت|ساعه|time)/u', $normHeader)) {
                $values[] = (string) ($appointment->appointment_time ?? '');
            } else {
                $values[] = '';
            }
        }

        return $values;
    }

    protected function extractNameFromFormData(array $formData): ?string
    {
        foreach ($formData as $k => $v) {
            $norm = $this->normalizeArabicText((string) $k);
            if (preg_match('/(اسم|الاسم|المريض|العميل|name)/u', $norm) && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        return null;
    }

    protected function normalizeArabicText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[إأآا]/u', 'ا', $text) ?? $text;
        $text = preg_replace('/[ةه]/u', 'ه', $text) ?? $text;
        $text = preg_replace('/[يى]/u', 'ي', $text) ?? $text;
        $text = preg_replace('/[\x{064B}-\x{0652}]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
