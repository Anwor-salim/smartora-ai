<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Services\AiChatService;
use App\Services\AppointmentBookingService;
use App\Services\DocumentParserService;
use App\Services\GoogleSheetsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function __construct(
        protected DocumentParserService $parserService
    ) {}

    protected function resolveCompany(Request $request): ?Company
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        if ($user->isCompanyAdmin()) {
            return $user->company;
        }

        return $user->company ?? Company::first();
    }

    /**
     * Company Dashboard KPIs, Charts, Onboarding, and Recent Activity.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بهذا الحساب'], 404);
        }

        $range = (string) $request->query('range', 'all');
        $tz = (string) ($company->settings['timezone'] ?? 'Asia/Riyadh');
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = 'Asia/Riyadh';
        }

        $fromDate = match ($range) {
            'today' => Carbon::today($tz)->startOfDay(),
            '7d' => Carbon::now($tz)->subDays(6)->startOfDay(),
            '30d' => Carbon::now($tz)->subDays(29)->startOfDay(),
            default => null,
        };

        $convBase = Conversation::where('company_id', $company->id);
        if ($fromDate) {
            $convBase->where('created_at', '>=', $fromDate->copy()->utc());
        }

        $apptBase = Appointment::where('company_id', $company->id);
        if ($fromDate) {
            $apptBase->where('created_at', '>=', $fromDate->copy()->utc());
        }

        $totalConversations = (clone $convBase)->count();
        $todayConversations = Conversation::where('company_id', $company->id)
            ->whereDate('last_interaction_at', Carbon::today($tz)->toDateString())
            ->count();
        $pausedConversations = (clone $convBase)->where('ai_paused', true)->count();
        $activeAiConversations = max(0, $totalConversations - $pausedConversations);
        $handoffConversations = (clone $convBase)->where('conversation_state', 'human_handoff')->count();

        $totalAppointments = (clone $apptBase)->count();
        $pendingAppointments = (clone $apptBase)->where('status', 'pending')->count();
        $approvedAppointments = (clone $apptBase)->where('status', 'approved')->count();
        $rejectedAppointments = (clone $apptBase)->where('status', 'rejected')->count();
        $cancelledAppointments = (clone $apptBase)->where('status', 'cancelled')->count();

        $readyDocuments = KnowledgeDocument::where('company_id', $company->id)->where('status', 'ready')->count();
        $totalItems = (int) KnowledgeDocument::where('company_id', $company->id)->where('status', 'ready')->sum('total_items');

        $waConnected = $company->isWhatsAppConnected();
        $igConnected = $company->isInstagramConnected();
        $fbConnected = $company->isMessengerConnected();
        $channelsCount = ($waConnected ? 1 : 0) + ($igConnected ? 1 : 0) + ($fbConnected ? 1 : 0);

        $isActive = $company->isSubscriptionActive();
        $daysRemaining = $company->subscription_expires_at
            ? max(0, (int) Carbon::now()->diffInDays($company->subscription_expires_at, false))
            : null;
        $planLabel = match ($company->subscription_plan) {
            'free_trial' => 'الباقة التجريبية',
            'monthly_pro' => 'الباقة الشهرية',
            '3_months' => 'باقة 3 أشهر',
            '6_months' => 'باقة 6 أشهر',
            'yearly' => 'الباقة السنوية',
            'enterprise' => 'باقة الشركات',
            default => 'الاشتراك',
        };

        // Channel distribution bar chart
        $waConvs = (clone $convBase)->where(fn ($q) => $q->where('channel', 'whatsapp')->orWhereNull('channel'))->count();
        $igConvs = (clone $convBase)->where('channel', 'instagram')->count();
        $fbConvs = (clone $convBase)->where('channel', 'messenger')->count();

        $daily7d = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::now($tz)->subDays($i);
            $dayStr = $day->toDateString();
            $daily7d[] = [
                'date' => $dayStr,
                'label' => $day->format('m/d'),
                'conversations' => Conversation::where('company_id', $company->id)->whereDate('created_at', $dayStr)->count(),
                'appointments' => Appointment::where('company_id', $company->id)->whereDate('created_at', $dayStr)->count(),
            ];
        }

        // Donut breakdowns
        $apptDenom = max(1, $totalAppointments);
        $appointmentsBreakdown = [
            'total' => $totalAppointments,
            'items' => [
                [
                    'key' => 'approved',
                    'label' => 'حجوزات مؤكدة',
                    'count' => $approvedAppointments,
                    'percent' => $totalAppointments > 0 ? round(($approvedAppointments / $apptDenom) * 100) : 0,
                    'color' => '#10B981',
                ],
                [
                    'key' => 'pending',
                    'label' => 'قيد المراجعة',
                    'count' => $pendingAppointments,
                    'percent' => $totalAppointments > 0 ? round(($pendingAppointments / $apptDenom) * 100) : 0,
                    'color' => '#F5B700',
                ],
                [
                    'key' => 'rejected',
                    'label' => 'مرفوضة',
                    'count' => $rejectedAppointments,
                    'percent' => $totalAppointments > 0 ? round(($rejectedAppointments / $apptDenom) * 100) : 0,
                    'color' => '#EF4444',
                ],
                [
                    'key' => 'cancelled',
                    'label' => 'ملغاة',
                    'count' => $cancelledAppointments,
                    'percent' => $totalAppointments > 0 ? round(($cancelledAppointments / $apptDenom) * 100) : 0,
                    'color' => '#64748B',
                ],
            ],
        ];

        $bookingStateCount = (clone $convBase)->where('conversation_state', 'booking')->count();
        $inquiringStateCount = (clone $convBase)->where('conversation_state', 'inquiring')->count();
        $browsingStateCount = max(0, $totalConversations - $bookingStateCount - $inquiringStateCount - $handoffConversations);
        $convDenom = max(1, $totalConversations);

        $conversationsBreakdown = [
            'total' => $totalConversations,
            'items' => [
                [
                    'key' => 'booking',
                    'label' => 'طلبات حجز',
                    'count' => $bookingStateCount,
                    'percent' => $totalConversations > 0 ? round(($bookingStateCount / $convDenom) * 100) : 0,
                    'color' => '#F5B700',
                ],
                [
                    'key' => 'inquiring',
                    'label' => 'استفسارات نشطة',
                    'count' => $inquiringStateCount,
                    'percent' => $totalConversations > 0 ? round(($inquiringStateCount / $convDenom) * 100) : 0,
                    'color' => '#0EA5E9',
                ],
                [
                    'key' => 'human_handoff',
                    'label' => 'تحويل لموظف',
                    'count' => $handoffConversations,
                    'percent' => $totalConversations > 0 ? round(($handoffConversations / $convDenom) * 100) : 0,
                    'color' => '#EF4444',
                ],
                [
                    'key' => 'browsing',
                    'label' => 'تصفح عام',
                    'count' => $browsingStateCount,
                    'percent' => $totalConversations > 0 ? round(($browsingStateCount / $convDenom) * 100) : 0,
                    'color' => '#1E293B',
                ],
            ],
        ];

        $recentAppointments = Appointment::where('company_id', $company->id)
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'booking_code' => $a->booking_code,
                'customer_name' => $a->customer_name,
                'customer_phone' => $a->customer_phone,
                'appointment_date' => $a->appointment_date?->format('Y-m-d'),
                'appointment_time' => $a->appointment_time,
                'queue_number' => $a->queue_number,
                'status' => $a->status,
                'status_label' => $a->statusLabelArabic(),
                'channel' => $a->channel ?: 'whatsapp',
                'created_at_human' => $a->created_at?->diffForHumans(),
            ]);

        $recentConversations = Conversation::where('company_id', $company->id)
            ->withCount('messages')
            ->latest('last_interaction_at')
            ->limit(5)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'channel' => $c->channel ?: 'whatsapp',
                'customer_phone' => $c->customer_phone,
                'customer_name' => $c->customer_name,
                'last_message' => $c->last_message,
                'conversation_state' => $c->conversation_state ?: 'browsing',
                'ai_paused' => (bool) $c->ai_paused,
                'messages_count' => $c->messages_count,
                'last_interaction_human' => $c->last_interaction_at?->diffForHumans(),
            ]);

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'whatsapp_number' => $company->whatsapp_number,
                'status' => $company->status,
                'is_active' => $isActive,
                'subscription_plan' => $company->subscription_plan,
                'plan_label' => $planLabel,
                'subscription_expires_at' => $company->subscription_expires_at?->format('Y-m-d'),
                'days_remaining' => $daysRemaining,
                'booking_enabled' => (bool) ($company->settings['booking_enabled'] ?? false),
            ],
            'onboarding' => $company->getOnboardingProgress(),
            'kpis' => [
                'total_appointments' => $totalAppointments,
                'pending_appointments' => $pendingAppointments,
                'approved_appointments' => $approvedAppointments,
                'total_conversations' => $totalConversations,
                'today_conversations' => $todayConversations,
                'active_ai_conversations' => $activeAiConversations,
                'paused_ai_conversations' => $pausedConversations,
                'handoff_conversations' => $handoffConversations,
                'ready_documents' => $readyDocuments,
                'total_items' => $totalItems,
                'connected_channels' => $channelsCount,
                'channels' => [
                    'whatsapp' => $waConnected,
                    'instagram' => $igConnected,
                    'messenger' => $fbConnected,
                ],
            ],
            'charts' => [
                'channels_bar' => [
                    ['key' => 'whatsapp', 'label' => 'واتساب', 'count' => $waConvs, 'color' => '#F5B700'],
                    ['key' => 'instagram', 'label' => 'انستقرام', 'count' => $igConvs, 'color' => '#EC4899'],
                    ['key' => 'messenger', 'label' => 'ماسنجر', 'count' => $fbConvs, 'color' => '#0EA5E9'],
                ],
                'daily_7d' => $daily7d,
                'appointments_breakdown' => $appointmentsBreakdown,
                'conversations_breakdown' => $conversationsBreakdown,
            ],
            'recent_appointments' => $recentAppointments,
            'recent_conversations' => $recentConversations,
        ]);
    }

    /**
     * Get Company Profile & Settings.
     */
    public function profile(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد شركة مرتبطة بهذا الحساب'], 404);
        }

        $isExpired = $company->subscription_expires_at && $company->subscription_expires_at->isPast();
        $settings = is_array($company->settings) ? $company->settings : [];
        $geminiKey = (string) ($settings['gemini_api_key'] ?? '');
        $hasValidGemini = $geminiKey !== '' && (str_starts_with($geminiKey, 'AIza') || str_starts_with($geminiKey, 'AQ.'));

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'about_us' => $company->about_us,
                'services_offered' => $company->services_offered,
                'phone' => $company->phone,
                'whatsapp_number' => $company->whatsapp_number,
                'support_phone' => $company->support_phone,
                'email' => $company->email,
                'address' => $company->address,
                'google_maps_url' => $company->google_maps_url,
                'working_hours' => $company->working_hours,
                'custom_system_prompt' => $company->custom_system_prompt,
                'status' => $company->status,
                'is_active' => $company->isSubscriptionActive(),
                'is_expired' => $isExpired,
                'subscription_plan' => $company->subscription_plan,
                'subscription_expires_at' => $company->subscription_expires_at?->format('Y-m-d'),
                'days_remaining' => $company->subscription_expires_at ? max(0, (int) Carbon::now()->diffInDays($company->subscription_expires_at, false)) : null,
                'settings' => [
                    'greeting_message' => $settings['greeting_message'] ?? '',
                    'gemini_api_key' => $hasValidGemini ? $geminiKey : '',
                    'gemini_api_key_set' => $hasValidGemini,
                    'response_tone' => $settings['response_tone'] ?? 'professional_friendly',
                    'memory_messages_limit' => (int) ($settings['memory_messages_limit'] ?? 12),
                    'timezone' => $settings['timezone'] ?? 'Asia/Riyadh',
                    'auto_pause_on_handoff' => (bool) ($settings['auto_pause_on_handoff'] ?? false),
                    'booking_enabled' => (bool) ($settings['booking_enabled'] ?? false),
                ],
            ],
            'stats' => [
                'documents_count' => $company->documents()->count(),
                'conversations_count' => $company->conversations()->count(),
                'appointments_count' => $company->appointments()->count(),
            ],
        ]);
    }

    /**
     * Update Company Profile, WhatsApp number, and AI Prompts/Settings.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد شركة مرتبطة بهذا الحساب'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'about_us' => 'nullable|string|max:3000',
            'services_offered' => 'nullable|string|max:3000',
            'phone' => 'nullable|string|max:40',
            'whatsapp_number' => 'nullable|string|max:40',
            'support_phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'google_maps_url' => 'nullable|string|max:500',
            'working_hours' => 'nullable|string|max:255',
            'custom_system_prompt' => 'nullable|string|max:5000',
            'greeting_message' => 'nullable|string|max:1000',
            'gemini_api_key' => 'nullable|string|max:255',
            'response_tone' => 'nullable|in:professional_friendly,formal,concise',
            'memory_messages_limit' => 'nullable|integer|min:4|max:24',
            'timezone' => 'nullable|string|max:64',
            'auto_pause_on_handoff' => 'nullable|boolean',
            'booking_enabled' => 'nullable|boolean',
        ]);

        $settings = is_array($company->settings) ? $company->settings : [];
        if ($request->has('greeting_message')) {
            $settings['greeting_message'] = (string) ($request->greeting_message ?? '');
        }
        if ($request->has('response_tone')) {
            $settings['response_tone'] = $request->response_tone ?: 'professional_friendly';
        }
        if ($request->has('memory_messages_limit')) {
            $settings['memory_messages_limit'] = (int) $request->memory_messages_limit;
        }
        if ($request->has('timezone')) {
            $settings['timezone'] = $request->timezone ?: 'Asia/Riyadh';
        }
        if ($request->has('auto_pause_on_handoff')) {
            $settings['auto_pause_on_handoff'] = (bool) $request->boolean('auto_pause_on_handoff');
        }
        if ($request->has('booking_enabled')) {
            $settings['booking_enabled'] = (bool) $request->boolean('booking_enabled');
        }

        $invalidKeyWarning = false;
        if ($request->has('gemini_api_key')) {
            $submittedKey = trim((string) ($request->gemini_api_key ?? ''));
            if ($submittedKey !== '' && !str_starts_with($submittedKey, 'AIza') && !str_starts_with($submittedKey, 'AQ.')) {
                $settings['gemini_api_key'] = '';
                $invalidKeyWarning = true;
            } else {
                $settings['gemini_api_key'] = $submittedKey;
            }
        }

        $company->update([
            'name' => $request->has('name') ? $request->name : $company->name,
            'about_us' => $request->has('about_us') ? $request->about_us : $company->about_us,
            'services_offered' => $request->has('services_offered') ? $request->services_offered : $company->services_offered,
            'phone' => $request->has('phone') ? $request->phone : $company->phone,
            'whatsapp_number' => $request->has('whatsapp_number') ? $request->whatsapp_number : $company->whatsapp_number,
            'support_phone' => $request->has('support_phone') ? $request->support_phone : $company->support_phone,
            'email' => $request->has('email') ? $request->email : $company->email,
            'address' => $request->has('address') ? $request->address : $company->address,
            'google_maps_url' => $request->has('google_maps_url') ? $request->google_maps_url : $company->google_maps_url,
            'working_hours' => $request->has('working_hours') ? $request->working_hours : $company->working_hours,
            'custom_system_prompt' => $request->has('custom_system_prompt') ? $request->custom_system_prompt : $company->custom_system_prompt,
            'settings' => $settings,
        ]);

        $company->markKnowledgeUpdated();

        return response()->json([
            'message' => $invalidKeyWarning
                ? 'تم حفظ بيانات المنشأة، لكن مفتاح Gemini المدخل غير صالح (يجب أن يبدأ بـ AIza أو AQ.)'
                : 'تم حفظ وتحديث بيانات المنشأة بنجاح!',
            'warning' => $invalidKeyWarning,
            'company' => $company->fresh(),
        ]);
    }

    /**
     * List uploaded documents.
     */
    public function documents(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['documents' => []]);
        }

        $query = $company->documents()->latest();

        $fileType = trim((string) $request->query('file_type', ''));
        if ($fileType !== '' && $fileType !== 'all') {
            if ($fileType === 'excel') {
                $query->whereIn('file_type', ['xlsx', 'xls']);
            } else {
                $query->where('file_type', strtolower($fileType));
            }
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $documents = $query->get()->map(function ($doc) {
            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'file_type' => $doc->file_type,
                'file_name' => basename((string) $doc->file_path),
                'file_size' => $doc->file_size,
                'formatted_size' => $doc->file_size ? round($doc->file_size / 1024, 1) . ' KB' : '0 KB',
                'total_items' => $doc->total_items ?? 0,
                'status' => $doc->status,
                'error_message' => $doc->error_message,
                'created_at' => $doc->created_at?->format('Y-m-d H:i'),
                'created_at_human' => $doc->created_at?->diffForHumans(),
                'extracted_text' => mb_substr((string) ($doc->extracted_text ?? ''), 0, 4000),
                'preview_data' => array_slice($doc->extracted_data ?: [], 0, 20),
            ];
        });

        return response()->json(['documents' => $documents]);
    }

    /**
     * Upload and Parse Excel / PDF Document.
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,pdf,txt|max:51200',
            'title' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $originalName = $file->getClientOriginalName();
        $title = trim((string) ($request->title ?: pathinfo($originalName, PATHINFO_FILENAME)));

        $path = $file->store('company_knowledge/' . $company->id, 'local');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $parsed = $this->parserService->parseFile($fullPath, $extension);

            $document = KnowledgeDocument::create([
                'company_id' => $company->id,
                'title' => $title,
                'file_type' => strtolower($extension),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'extracted_text' => $parsed['text'],
                'extracted_data' => $parsed['data'],
                'total_items' => $parsed['total_items'] ?? count($parsed['data'] ?? []),
                'status' => 'ready',
            ]);

            return response()->json([
                'message' => 'تم رفع الملف ومعالجة البيانات بنجاح، وأصبح الذكاء الاصطناعي قادراً على الإجابة منه فوراً!',
                'document' => $document,
            ], 201);
        } catch (\Throwable $e) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            return response()->json([
                'message' => 'فشلت معالجة الملف: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Re-parse an existing Knowledge Document.
     */
    public function reparseDocument(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $doc = KnowledgeDocument::where('company_id', $company->id)->findOrFail($id);

        try {
            $fullPath = Storage::disk('local')->path($doc->file_path);
            $ext = pathinfo($doc->file_path, PATHINFO_EXTENSION);
            $parsed = $this->parserService->parseFile($fullPath, $ext);

            $doc->update([
                'extracted_text' => $parsed['text'],
                'extracted_data' => $parsed['data'],
                'total_items' => $parsed['total_items'] ?? count($parsed['data'] ?? []),
                'status' => 'ready',
                'error_message' => null,
            ]);

            return response()->json([
                'message' => 'تمت إعادة تحليل الملف بنجاح!',
                'document' => $doc->fresh(),
            ]);
        } catch (\Throwable $e) {
            $doc->update(['status' => 'error', 'error_message' => $e->getMessage()]);

            return response()->json([
                'message' => 'فشل تحليل الملف: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a Document.
     */
    public function deleteDocument(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $doc = KnowledgeDocument::where('company_id', $company->id)->findOrFail($id);

        if (Storage::disk('local')->exists($doc->file_path)) {
            Storage::disk('local')->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json(['message' => 'تم حذف المستند من قاعدة المعرفة بنجاح']);
    }

    /**
     * List Appointments with filters & tab counts.
     */
    public function appointments(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['appointments' => [], 'counts' => []]);
        }

        $countsBase = Appointment::where('company_id', $company->id);
        $counts = [
            'all' => (clone $countsBase)->count(),
            'pending' => (clone $countsBase)->where('status', 'pending')->count(),
            'approved' => (clone $countsBase)->where('status', 'approved')->count(),
            'rejected' => (clone $countsBase)->where('status', 'rejected')->count(),
            'cancelled' => (clone $countsBase)->where('status', 'cancelled')->count(),
        ];

        $query = Appointment::where('company_id', $company->id)->latest('created_at');

        $status = trim((string) $request->query('status', 'all'));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $channel = trim((string) $request->query('channel', 'all'));
        if ($channel !== '' && $channel !== 'all') {
            $query->where('channel', $channel);
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        if ($dateFrom !== '') {
            $query->whereDate('appointment_date', '>=', $dateFrom);
        }

        $dateTo = trim((string) $request->query('date_to', ''));
        if ($dateTo !== '') {
            $query->whereDate('appointment_date', '<=', $dateTo);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $appointments = $query->get()->map(fn (Appointment $a) => [
            'id' => $a->id,
            'booking_code' => $a->booking_code,
            'queue_number' => $a->queue_number,
            'channel' => $a->channel ?: 'whatsapp',
            'customer_name' => $a->customer_name,
            'customer_phone' => $a->customer_phone,
            'appointment_date' => $a->appointment_date?->format('Y-m-d'),
            'appointment_time' => $a->appointment_time,
            'status' => $a->status,
            'status_label' => $a->statusLabelArabic(),
            'rejection_reason' => $a->rejection_reason,
            'sheet_row_number' => $a->sheet_row_number,
            'sheet_synced_at' => $a->sheet_synced_at?->format('Y-m-d H:i'),
            'form_data' => $a->form_data ?? [],
            'created_at' => $a->created_at?->format('Y-m-d H:i'),
            'created_at_human' => $a->created_at?->diffForHumans(),
        ]);

        return response()->json([
            'appointments' => $appointments,
            'counts' => $counts,
        ]);
    }

    public function appointmentDetail(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $appt = Appointment::where('company_id', $company->id)->findOrFail($id);

        return response()->json([
            'appointment' => [
                'id' => $appt->id,
                'booking_code' => $appt->booking_code,
                'queue_number' => $appt->queue_number,
                'channel' => $appt->channel ?: 'whatsapp',
                'customer_name' => $appt->customer_name,
                'customer_phone' => $appt->customer_phone,
                'appointment_date' => $appt->appointment_date?->format('Y-m-d'),
                'appointment_time' => $appt->appointment_time,
                'status' => $appt->status,
                'status_label' => $appt->statusLabelArabic(),
                'rejection_reason' => $appt->rejection_reason,
                'sheet_row_number' => $appt->sheet_row_number,
                'form_data' => $appt->form_data ?? [],
                'created_at' => $appt->created_at?->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function approveAppointment(Request $request, int $id, AppointmentBookingService $bookingService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $appt = Appointment::where('company_id', $company->id)->findOrFail($id);
        $bookingService->approveAppointment($appt);

        return response()->json([
            'message' => 'تم قبول الموعد وتحديث الشيت وإرسال التأكيد للعميل',
            'appointment' => $appt->fresh(),
        ]);
    }

    public function rejectAppointment(Request $request, int $id, AppointmentBookingService $bookingService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $appt = Appointment::where('company_id', $company->id)->findOrFail($id);
        $bookingService->rejectAppointment($appt, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'تم رفض الطلب وإشعار العميل وإفراغ المقعد',
            'appointment' => $appt->fresh(),
        ]);
    }

    public function cancelAppointment(Request $request, int $id, AppointmentBookingService $bookingService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $appt = Appointment::where('company_id', $company->id)->findOrFail($id);
        $bookingService->cancelAppointment($appt, 'reception');

        return response()->json([
            'message' => 'تم إلغاء الموعد وإشعار العميل',
            'appointment' => $appt->fresh(),
        ]);
    }

    public function syncAppointmentSheets(Request $request, AppointmentBookingService $bookingService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $stats = $bookingService->syncCompanySheetStatuses($company);
        if (!empty($stats['skipped'])) {
            return response()->json([
                'message' => 'تعذر المزامنة: تأكد من تهيئة Google Service Account وربط ملف Google Sheet أولاً.',
                'stats' => $stats,
            ], 422);
        }

        return response()->json([
            'message' => "تمت المزامنة: مقبول ({$stats['approved']}) | مرفوض ({$stats['rejected']}) | ملغي ({$stats['cancelled']})",
            'stats' => $stats,
        ]);
    }

    /**
     * Booking & Google Sheets Settings.
     */
    public function bookingSettings(Request $request, GoogleSheetsService $sheets): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد شركة مرتبطة بهذا الحساب'], 404);
        }

        $settings = is_array($company->settings) ? $company->settings : [];
        $customFields = $settings['booking_custom_fields'] ?? ['الاسم', 'العمر', 'التاريخ', 'الوقت'];
        $customFieldsStr = is_array($customFields) ? implode('، ', $customFields) : (string) $customFields;

        return response()->json([
            'booking_enabled' => (bool) ($settings['booking_enabled'] ?? false),
            'booking_approval_mode' => $settings['booking_approval_mode'] ?? 'reception_approval',
            'booking_notify_reception_whatsapp' => (bool) ($settings['booking_notify_reception_whatsapp'] ?? false),
            'booking_daily_limit' => (int) ($settings['booking_daily_limit'] ?? 10),
            'booking_working_days' => $settings['booking_working_days'] ?? ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
            'booking_date_overrides' => $settings['booking_date_overrides'] ?? new \stdClass(),
            'booking_sheet_url' => $settings['booking_sheet_url'] ?? '',
            'booking_sheet_id' => $settings['booking_sheet_id'] ?? null,
            'booking_sheet_tab' => $settings['booking_sheet_tab'] ?? '',
            'booking_sheet_headers' => $settings['booking_sheet_headers'] ?? [],
            'booking_custom_fields' => $customFieldsStr,
            'service_account_configured' => $sheets->isConfigured(),
            'service_account_email' => $sheets->getServiceAccountEmail() ?: 'لم يتم تهيئة ملف Google Service Account على السيرفر بعد',
        ]);
    }

    public function updateBookingSettings(Request $request, GoogleSheetsService $sheets): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد شركة مرتبطة بهذا الحساب'], 404);
        }

        $validated = $request->validate([
            'booking_enabled' => 'required|boolean',
            'booking_approval_mode' => 'required|in:reception_approval,auto_confirm',
            'booking_notify_reception_whatsapp' => 'required|boolean',
            'booking_daily_limit' => 'required|integer|min:1|max:500',
            'booking_working_days' => 'required|array|min:1',
            'booking_working_days.*' => 'in:sat,sun,mon,tue,wed,thu,fri',
            'booking_date_overrides' => 'nullable|array',
            'booking_sheet_url' => 'nullable|string|max:1000',
            'booking_sheet_tab' => 'nullable|string|max:120',
            'booking_custom_fields' => 'nullable|string|max:1000',
        ]);

        $settings = is_array($company->settings) ? $company->settings : [];
        $sheetUrl = trim((string) ($validated['booking_sheet_url'] ?? ''));
        $sheetId = $sheets->extractSpreadsheetId($sheetUrl);

        $customFields = array_values(array_filter(array_map(
            'trim',
            preg_split('/[,،\n]+/u', (string) ($validated['booking_custom_fields'] ?? '')) ?: []
        )));

        $overrides = [];
        if (!empty($validated['booking_date_overrides']) && is_array($validated['booking_date_overrides'])) {
            foreach ($validated['booking_date_overrides'] as $dateKey => $limitVal) {
                $cleanDate = trim((string) $dateKey);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cleanDate) && is_numeric($limitVal)) {
                    $overrides[$cleanDate] = max(0, (int) $limitVal);
                }
            }
        }

        $settings['booking_enabled'] = (bool) $validated['booking_enabled'];
        $settings['booking_approval_mode'] = $validated['booking_approval_mode'];
        $settings['booking_notify_reception_whatsapp'] = (bool) $validated['booking_notify_reception_whatsapp'];
        $settings['booking_daily_limit'] = max(1, (int) $validated['booking_daily_limit']);
        $settings['booking_working_days'] = array_values($validated['booking_working_days']);
        $settings['booking_date_overrides'] = $overrides;
        $settings['booking_sheet_url'] = $sheetUrl;
        $settings['booking_sheet_id'] = $sheetId;
        $settings['booking_sheet_tab'] = trim((string) ($validated['booking_sheet_tab'] ?? ''));
        $settings['booking_custom_fields'] = !empty($customFields) ? $customFields : ['الاسم', 'العمر', 'التاريخ', 'الوقت'];

        $company->update(['settings' => $settings]);

        return response()->json([
            'message' => 'تم حفظ إعدادات الحجز و Google Sheets بنجاح!',
            'booking_sheet_id' => $sheetId,
        ]);
    }

    public function syncBookingHeaders(Request $request, GoogleSheetsService $sheets, AppointmentBookingService $bookingService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد شركة مرتبطة بهذا الحساب'], 404);
        }

        if ($request->has('booking_sheet_url') || $request->has('booking_sheet_tab')) {
            $settings = is_array($company->settings) ? $company->settings : [];
            if ($request->has('booking_sheet_url')) {
                $sheetUrl = trim((string) ($request->booking_sheet_url ?? ''));
                $settings['booking_sheet_url'] = $sheetUrl;
                $settings['booking_sheet_id'] = $sheets->extractSpreadsheetId($sheetUrl);
            }
            if ($request->has('booking_sheet_tab')) {
                $settings['booking_sheet_tab'] = trim((string) ($request->booking_sheet_tab ?? ''));
            }
            $company->update(['settings' => $settings]);
            $company->refresh();
        }

        if (!$sheets->isConfigured()) {
            return response()->json([
                'message' => 'حساب الخدمة (Service Account) غير مهيأ على السيرفر بعد.',
            ], 422);
        }

        $schema = $bookingService->refreshSheetHeaders($company, true);
        if (!in_array($schema['source'] ?? '', ['google_sheets', 'google_sheets_cached'], true)) {
            return response()->json([
                'message' => 'تعذر قراءة ملف Google Sheet. تأكد من صحة رابط الملف ومشاركته مع بريد الخدمة كـ Editor.',
            ], 422);
        }

        $labels = array_map(fn ($f) => $f['label'], $schema['customer_fields'] ?? []);

        return response()->json([
            'message' => 'تمت مزامنة أعمدة Google Sheet بنجاح! الحقول: ' . (!empty($labels) ? implode('، ', $labels) : 'لا توجد حقول عميل'),
            'headers' => $schema['all'] ?? [],
            'customer_fields' => $schema['customer_fields'] ?? [],
        ]);
    }

    /**
     * Customer Conversations list with filters & channel counts.
     */
    public function conversations(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['conversations' => [], 'counts' => []]);
        }

        $base = $company->conversations();
        $counts = [
            'all' => (clone $base)->count(),
            'whatsapp' => (clone $base)->where(fn ($q) => $q->where('channel', 'whatsapp')->orWhereNull('channel'))->count(),
            'instagram' => (clone $base)->where('channel', 'instagram')->count(),
            'messenger' => (clone $base)->where('channel', 'messenger')->count(),
        ];

        $query = $company->conversations()
            ->withCount('messages')
            ->latest('last_interaction_at');

        $channel = trim((string) $request->query('channel', 'all'));
        if ($channel !== '' && $channel !== 'all') {
            if ($channel === 'whatsapp') {
                $query->where(fn ($q) => $q->where('channel', 'whatsapp')->orWhereNull('channel'));
            } else {
                $query->where('channel', $channel);
            }
        }

        $state = trim((string) $request->query('state', 'all'));
        if ($state !== '' && $state !== 'all') {
            $query->where('conversation_state', $state);
        }

        $aiPaused = trim((string) $request->query('ai_paused', 'all'));
        if ($aiPaused === 'paused') {
            $query->where('ai_paused', true);
        } elseif ($aiPaused === 'active') {
            $query->where('ai_paused', false);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('last_message', 'like', "%{$search}%");
            });
        }

        $conversations = $query->get()->map(fn (Conversation $c) => [
            'id' => $c->id,
            'channel' => $c->channel ?: 'whatsapp',
            'customer_phone' => $c->customer_phone,
            'clean_identifier' => (string) preg_replace('/^(ig|fb):/', '', (string) $c->customer_phone),
            'customer_name' => $c->customer_name,
            'last_message' => $c->last_message,
            'conversation_state' => $c->conversation_state ?: 'browsing',
            'ai_paused' => (bool) $c->ai_paused,
            'messages_count' => $c->messages_count,
            'last_interaction_at' => $c->last_interaction_at?->format('Y-m-d H:i'),
            'last_interaction_human' => $c->last_interaction_at?->diffForHumans(),
        ]);

        return response()->json([
            'conversations' => $conversations,
            'counts' => $counts,
        ]);
    }

    /**
     * Conversation Detail & Chat History.
     */
    public function conversationDetail(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $conversation = Conversation::where('company_id', $company->id)
            ->with(['messages' => fn ($q) => $q->orderBy('id')])
            ->findOrFail($id);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'channel' => $conversation->channel ?: 'whatsapp',
                'customer_phone' => $conversation->customer_phone,
                'clean_identifier' => (string) preg_replace('/^(ig|fb):/', '', (string) $conversation->customer_phone),
                'customer_name' => $conversation->customer_name,
                'conversation_state' => $conversation->conversation_state ?: 'browsing',
                'ai_paused' => (bool) $conversation->ai_paused,
                'summary' => $conversation->summary,
                'last_interaction_at' => $conversation->last_interaction_at?->format('Y-m-d H:i'),
                'messages' => $conversation->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'sender' => $m->sender,
                    'message' => $m->message,
                    'created_at' => $m->created_at?->format('d/m H:i'),
                    'metadata' => $m->metadata ?? [],
                ]),
            ],
        ]);
    }

    public function toggleConversationPause(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $conversation = Conversation::where('company_id', $company->id)->findOrFail($id);
        $newPaused = !$conversation->ai_paused;
        $conversation->update([
            'ai_paused' => $newPaused,
            'ai_paused_at' => $newPaused ? now() : null,
        ]);

        return response()->json([
            'message' => $newPaused ? 'تم إيقاف البوت لهذه المحادثة' : 'تم إعادة تفعيل البوت للمحادثة',
            'ai_paused' => $newPaused,
        ]);
    }

    public function resetConversationContext(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $conversation = Conversation::where('company_id', $company->id)->findOrFail($id);
        $conversation->resetContext();

        return response()->json([
            'message' => 'تم تصفير سياق المحادثة بنجاح',
        ]);
    }

    /**
     * AI Simulator Send & Reset.
     */
    public function simulatorSend(Request $request, AiChatService $aiService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بحسابك'], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'customer_phone' => 'nullable|string|max:40',
        ]);

        $phone = trim((string) ($validated['customer_phone'] ?? '')) ?: '96777000888';
        $res = $aiService->handleCustomerMessage($company, $phone, trim($validated['message']));

        return response()->json([
            'reply' => $res['reply'] ?? 'لم يتم استلام رد من المحرك.',
            'status' => $res['status'] ?? 'success',
            'engine' => $res['engine'] ?? null,
            'conversation_state' => $res['conversation_state'] ?? 'browsing',
            'time' => now()->format('H:i'),
        ]);
    }

    public function simulatorReset(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['message' => 'لا توجد منشأة مرتبطة بحسابك'], 404);
        }

        $phone = trim((string) $request->input('customer_phone', '')) ?: '96777000888';
        $conversation = Conversation::where('company_id', $company->id)
            ->where('customer_phone', $phone)
            ->first();

        if ($conversation) {
            $conversation->messages()->delete();
            $conversation->resetContext();
            $conversation->update([
                'last_message' => null,
                'last_interaction_at' => null,
            ]);
        }

        $greeting = $company->settings['greeting_message']
            ?: "مرحباً بك في {$company->name}! كيف يمكننا خدمتك اليوم؟";

        return response()->json([
            'message' => 'تم إعادة ضبط محادثة المحاكي',
            'greeting' => $greeting,
            'time' => now()->format('H:i'),
        ]);
    }

    /**
     * Allow Super Admin to switch active company context from the Nuxt Company Portal header.
     */
    public function switchCompany(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $user->update(['company_id' => (int) $validated['company_id']]);
        $company = Company::find($validated['company_id']);

        return response()->json([
            'message' => "تم التبديل إلى منشأة: {$company->name}",
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status,
                'whatsapp_number' => $company->whatsapp_number,
                'is_active' => $company->isSubscriptionActive(),
            ],
        ]);
    }
}
