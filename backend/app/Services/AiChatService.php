<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SystemNotification;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    /**
     * Process an incoming message for a company from a customer on WhatsApp, Instagram, or Messenger.
     */
    public function handleCustomerMessage(
        Company $company,
        string $customerPhone,
        string $incomingMessage,
        string $channel = 'whatsapp',
        ?string $customerName = null
    ): array {
        // 1. Check Subscription Status
        if (!$company->isSubscriptionActive()) {
            return [
                'status' => 'suspended',
                'reply' => 'عذراً، خدمة الرد الآلي متوقفة حالياً لدى هذه المنشأة. يرجى التواصل مع الإدارة مباشرة.',
                'company' => $company->name,
            ];
        }

        // 2. Find or Create Conversation
        $isNewCustomer = false;
        $defaultCustomerName = $customerName ?: match ($channel) {
            'instagram' => 'عميل انستقرام',
            'messenger' => 'عميل ماسنجر',
            default => 'عميل واتساب (' . substr($customerPhone, -4) . ')',
        };

        $conversation = Conversation::firstOrCreate(
            [
                'company_id' => $company->id,
                'customer_phone' => $customerPhone,
            ],
            [
                'channel' => $channel,
                'customer_name' => $defaultCustomerName,
                'status' => 'active',
                'conversation_state' => 'browsing',
            ]
        );

        $convUpdates = [];
        if (empty($conversation->channel)) {
            $convUpdates['channel'] = $channel;
        }
        if (empty($conversation->conversation_state)) {
            $convUpdates['conversation_state'] = 'browsing';
        }
        if ($customerName && (in_array($conversation->customer_name, ['عميل انستقرام', 'عميل ماسنجر'], true) || empty($conversation->customer_name))) {
            $convUpdates['customer_name'] = $customerName;
        }
        if (!empty($convUpdates)) {
            $conversation->update($convUpdates);
        }

        if ($conversation->wasRecentlyCreated) {
            $isNewCustomer = true;
            $channelLabel = match ($channel) {
                'instagram' => 'انستقرام',
                'messenger' => 'ماسنجر فيسبوك',
                default => 'واتساب',
            };
            SystemNotification::create([
                'company_id' => $company->id,
                'title' => "عميل جديد يتواصل عبر {$channelLabel}",
                'message' => "تواصل عميل جديد ({$defaultCustomerName} - {$customerPhone}) مع شركة ({$company->name}) عبر {$channelLabel}",
                'type' => 'new_customer',
                'data' => [
                    'channel' => $channel,
                    'phone' => $customerPhone,
                    'company_id' => $company->id,
                    'first_message' => $incomingMessage,
                ],
            ]);
        }

        // Determine if this is a fresh session before persisting the new incoming message
        $hasPriorBotReply = $conversation->messages()->where('sender', 'bot')->exists();
        $isSessionStart = $isNewCustomer
            || !$hasPriorBotReply
            || !$conversation->last_interaction_at
            || $conversation->last_interaction_at->lt(Carbon::now()->subHours(6));

        // Save incoming customer message
        $incomingMsgRecord = Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'message' => $incomingMessage,
        ]);

        // 3. Respect manual / handoff AI pause
        if ($conversation->isAiPaused()) {
            $conversation->update([
                'last_message' => $incomingMessage,
                'last_interaction_at' => Carbon::now(),
            ]);

            return [
                'status' => 'paused',
                'reply' => null,
                'is_new_customer' => $isNewCustomer,
                'conversation_id' => $conversation->id,
                'conversation_state' => $conversation->conversation_state ?: 'browsing',
            ];
        }

        // 4. Detect explicit request to speak with a human agent
        if ($this->detectHumanHandoffRequest($incomingMessage)) {
            $channelLabel = match ($channel) {
                'instagram' => 'انستقرام',
                'messenger' => 'ماسنجر فيسبوك',
                default => 'واتساب',
            };
            $displayCustomer = $conversation->customer_name ?: $customerPhone;

            SystemNotification::create([
                'company_id' => $company->id,
                'title' => 'طلب تحويل لموظف بشري',
                'message' => "طلب العميل ({$displayCustomer} - {$customerPhone}) التحدث مع موظف بشري عبر {$channelLabel}. الرسالة: \"{$incomingMessage}\"",
                'type' => 'human_handoff',
                'data' => [
                    'channel' => $channel,
                    'phone' => $customerPhone,
                    'company_id' => $company->id,
                    'conversation_id' => $conversation->id,
                    'reason' => $incomingMessage,
                ],
            ]);

            $contextData = is_array($conversation->context_data) ? $conversation->context_data : [];
            $contextData['last_intent'] = 'human_handoff';
            $contextData['handoff_reason'] = $incomingMessage;
            $contextData['handoff_at'] = Carbon::now()->toIso8601String();
            $contextData['turns_count'] = ((int) ($contextData['turns_count'] ?? 0)) + 1;
            $contextData['last_engine'] = 'handoff_router';

            $handoffUpdate = [
                'conversation_state' => 'human_handoff',
                'context_data' => $contextData,
                'last_message' => $incomingMessage,
                'last_interaction_at' => Carbon::now(),
            ];

            if (!empty($company->settings['auto_pause_on_handoff'])) {
                $handoffUpdate['ai_paused'] = true;
                $handoffUpdate['ai_paused_at'] = Carbon::now();
            }

            $conversation->update($handoffUpdate);

            $supportContact = $company->support_phone ?: ($company->phone ?: $company->whatsapp_number);
            $handoffReply = "بكل سرور! تم إرسال طلبك وتنبيه فريق الدعم في {$company->name} للتواصل معك في أقرب وقت.";
            if ($supportContact) {
                $handoffReply .= "\n📞 وللتواصل المباشر الفوري: {$supportContact}";
            }

            Message::create([
                'conversation_id' => $conversation->id,
                'sender' => 'bot',
                'message' => $handoffReply,
                'metadata' => [
                    'engine' => 'handoff_router',
                    'intent' => 'human_handoff',
                    'has_knowledge_base' => $company->documents()->count() > 0,
                ],
            ]);

            return [
                'status' => 'success',
                'reply' => $handoffReply,
                'is_new_customer' => $isNewCustomer,
                'conversation_id' => $conversation->id,
                'conversation_state' => 'human_handoff',
                'engine' => 'handoff_router',
            ];
        }

        // 5. Load Recent Conversation Memory
        $memoryLimit = max(4, min(20, (int) ($company->settings['memory_messages_limit'] ?? 12)));
        $history = $conversation->getRecentHistory($memoryLimit, 24, $incomingMsgRecord->id);

        // 6. Assemble Dynamic Knowledge Context
        $knowledgeContext = $this->buildKnowledgeContext($company, $incomingMessage, $conversation, $history);

        // 7. Generate Context-Aware AI Response
        $generated = $this->generateResponse(
            $company,
            $incomingMessage,
            $knowledgeContext,
            $conversation,
            $history,
            $isSessionStart
        );

        $reply = $generated['reply'];
        $engine = $generated['engine'];
        $meta = $generated['meta'] ?? [];

        // 8. Persist Updated Conversation Context, State & Rolling Summary
        $updatedState = $this->updateConversationContext(
            $conversation,
            $company,
            $incomingMessage,
            $reply,
            $engine,
            $meta
        );

        // Save outgoing bot reply
        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'bot',
            'message' => $reply,
            'metadata' => [
                'engine' => $engine,
                'intent' => $meta['intent'] ?? ($conversation->context_data['last_intent'] ?? null),
                'has_knowledge_base' => $company->documents()->count() > 0,
            ],
        ]);

        // Update conversation last interaction
        $conversation->update([
            'last_message' => $incomingMessage,
            'last_interaction_at' => Carbon::now(),
        ]);

        return [
            'status' => 'success',
            'reply' => $reply,
            'is_new_customer' => $isNewCustomer,
            'conversation_id' => $conversation->id,
            'conversation_state' => $updatedState,
            'engine' => $engine,
        ];
    }

    /**
     * Resolve an API key honoring explicit getenv() overrides (including empty string in tests).
     */
    protected function resolveApiKey(string $envName, ?string $companyKey = null): ?string
    {
        $envRaw = getenv($envName);
        if ($envRaw !== false) {
            $trimmedEnv = trim((string) $envRaw);
            if ($trimmedEnv !== '') {
                return $trimmedEnv;
            }
        } else {
            $laravelEnv = trim((string) env($envName, ''));
            if ($laravelEnv !== '') {
                return $laravelEnv;
            }
        }

        $trimmedCompany = trim((string) ($companyKey ?? ''));
        return $trimmedCompany !== '' ? $trimmedCompany : null;
    }

    /**
     * Detect explicit customer requests to transfer or speak with a human agent.
     */
    protected function detectHumanHandoffRequest(string $message): bool
    {
        $norm = $this->normalizeArabic($message);
        $lower = mb_strtolower(trim($message));

        if (preg_match('/(كلم موظف|اكلم موظف|التحدث مع موظف|اتحدث مع موظف|حولني لموظف|تحويل لموظف|موظف بشري|دعم بشري|اريد مسؤول|اكلم الاداره|اكلم المدير|خدمه عملاء بشري|مندوب مبيعات|شخص حقيقي|تواصل مع موظف)/u', $norm)) {
            return true;
        }

        if (preg_match('/\b(talk to human|speak to human|human agent|real person|live agent|speak to someone|transfer to agent|customer support agent)\b/i', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * Build knowledge base context dynamically from company profile and uploaded documents.
     */
    protected function buildKnowledgeContext(
        Company $company,
        ?string $incomingMessage = null,
        ?Conversation $conversation = null,
        ?Collection $history = null
    ): string {
        $contextParts = [];

        $contextParts[] = "معلومات وبيانات المنشأة:";
        $contextParts[] = "- اسم المنشأة: " . $company->name;
        if ($company->about_us) $contextParts[] = "- نبذة عن المنشأة ونشاطها: " . $company->about_us;
        if ($company->services_offered) $contextParts[] = "- الخدمات والمنتجات التي تقدمها: " . $company->services_offered;
        if ($company->phone) $contextParts[] = "- رقم التواصل: " . $company->phone;
        if ($company->whatsapp_number) $contextParts[] = "- رقم الواتساب المربوط: " . $company->whatsapp_number;
        if ($company->support_phone) $contextParts[] = "- رقم مسؤول المبيعات المباشر / التحويل للدعم البشري: " . $company->support_phone;
        if ($company->address) $contextParts[] = "- الموقع / العنوان: " . $company->address;
        if ($company->google_maps_url) $contextParts[] = "- رابط الموقع على الخريطة: " . $company->google_maps_url;
        if ($company->working_hours) $contextParts[] = "- أوقات وساعات الدوام: " . $company->working_hours;

        // Retrieve uploaded Excel / PDF / Word documents for this company
        $documents = $company->documents()->where('status', 'ready')->get();
        if ($documents->count() > 0) {
            $maxChars = 24000;
            $totalLen = 0;
            foreach ($documents as $doc) {
                $totalLen += mb_strlen((string) $doc->extracted_text);
            }

            $contextParts[] = "\n--- قوائم الأسعار والبيانات والخدمات المرفقة من ملفات المنشأة ---";

            if ($totalLen <= $maxChars) {
                foreach ($documents as $doc) {
                    $contextParts[] = "### مستند ({$doc->title}) [نوع: {$doc->file_type}]:";
                    $contextParts[] = $doc->extracted_text;
                }
            } else {
                // Prioritize rows/sections relevant to current message, recent customer messages, and last topic
                $querySeedParts = array_filter([
                    $incomingMessage,
                    $conversation?->context_data['last_topic'] ?? null,
                    $conversation?->context_data['last_matched_title'] ?? null,
                ]);
                if ($history) {
                    foreach ($history->where('sender', 'customer')->take(-3) as $msg) {
                        $querySeedParts[] = $msg->message;
                    }
                }
                $combinedNormQuery = $this->normalizeArabic(implode(' ', $querySeedParts));
                $matchedRows = $combinedNormQuery !== '' ? $this->searchDynamicDocumentRows($documents, $combinedNormQuery) : [];

                $usedChars = 0;
                if (!empty($matchedRows)) {
                    $contextParts[] = "### البنود الأكثر صلة باستفسار العميل وسياق المحادثة:";
                    foreach (array_slice($matchedRows, 0, 25) as $row) {
                        unset($row['_score'], $row['_num_price']);
                        $line = '- ' . implode(' | ', array_map(
                            fn ($k, $v) => "{$k}: {$v}",
                            array_keys($row),
                            array_values($row)
                        ));
                        $lineLen = mb_strlen($line);
                        if ($usedChars + $lineLen > $maxChars) {
                            break;
                        }
                        $contextParts[] = $line;
                        $usedChars += $lineLen;
                    }
                }

                foreach ($documents as $doc) {
                    if ($usedChars >= $maxChars) {
                        break;
                    }
                    $remaining = $maxChars - $usedChars;
                    $snippet = mb_substr((string) $doc->extracted_text, 0, $remaining);
                    if ($snippet !== '') {
                        $contextParts[] = "### مستند ({$doc->title}) [نوع: {$doc->file_type}]:";
                        $contextParts[] = $snippet;
                        $usedChars += mb_strlen($snippet);
                    }
                }
            }
        }

        return implode("\n", $contextParts);
    }

    /**
     * Build the context-aware system instruction for LLM engines.
     */
    protected function buildSystemPrompt(
        Company $company,
        string $knowledgeContext,
        Conversation $conversation,
        bool $isSessionStart
    ): string {
        $timezone = (string) ($company->settings['timezone'] ?? 'Asia/Riyadh');
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $timezone = 'Asia/Riyadh';
        }

        $now = Carbon::now($timezone);
        $arabicDays = [
            0 => 'الأحد',
            1 => 'الإثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];
        $dayName = $arabicDays[(int) $now->dayOfWeek] ?? '';
        $dateTimeStr = "{$dayName} {$now->format('Y-m-d H:i')} ({$timezone})";

        $channelArabic = match ($conversation->channel ?? 'whatsapp') {
            'instagram' => 'انستقرام',
            'messenger' => 'ماسنجر فيسبوك',
            default => 'الواتساب',
        };

        $toneKey = (string) ($company->settings['response_tone'] ?? 'professional_friendly');
        $toneInstruction = match ($toneKey) {
            'formal' => 'استخدم أسلوباً رسمياً محترماً ومباشراً بالعربية الفصحى المبسطة بدون مبالغة.',
            'concise' => 'اجعل الردود مختصرة جداً ومباشرة في سطور قليلة سريعة القراءة بدون أي حشو.',
            default => 'استخدم أسلوباً ودوداً ومهنياً يعكس رقي خدمة العملاء ويفهم لهجة العميل بذكاء.',
        };

        $sessionInstruction = $isSessionStart
            ? "بداية محادثة جديدة — ابدأ بترحيب مختصر ولطيف باسم المنشأة ({$company->name}) ثم أجب مباشرة."
            : "محادثة مستمرة — لا تكرر الترحيب أو تعريف المنشأة أو العنوان ورقم الهاتف في كل رسالة؛ ادخل في الإجابة مباشرة ولا تذكر الموقع أو الرقم إلا إذا سأل العميل أو عند تأكيد حجز/طلب.";

        $contextData = is_array($conversation->context_data) ? $conversation->context_data : [];
        $currentState = $conversation->conversation_state ?: 'browsing';

        $basePrompt = $company->custom_system_prompt
            ?: "أنت المساعد الذكي والممثل الرسمي لخدمة عملاء منشأة ({$company->name}) عبر {$channelArabic}.";

        $lines = [
            $basePrompt,
            '',
            '[سياق الجلسة الحالي / Live Conversation Context]:',
            "- التاريخ والوقت الحالي: {$dateTimeStr}",
            "- قناة التواصل: {$channelArabic}",
            '- اسم العميل: ' . ($conversation->customer_name ?: 'غير محدد'),
            "- حالة الجلسة: {$sessionInstruction}",
            "- مرحلة المحادثة الحالية: {$currentState}",
        ];

        if (!empty($conversation->summary)) {
            $lines[] = '- ملخص المحادثة السابقة: ' . $conversation->summary;
        }
        if (!empty($contextData['last_matched_title']) || !empty($contextData['last_topic'])) {
            $topic = $contextData['last_matched_title'] ?? $contextData['last_topic'];
            $lines[] = "- آخر موضوع/صنف كان العميل يسأل عنه: {$topic}";
        }
        if (!empty($contextData['mentioned_budget'])) {
            $lines[] = '- الميزانية التي ذكرها العميل: ' . number_format((float) $contextData['mentioned_budget']) . ' ريال';
        }

        $lines[] = '';
        $lines[] = '[إرشادات وقواعد الرد المعتمد]:';
        $lines[] = '1. اقرأ كامل سياق الرسائل السابقة واربط الأسئلة القصيرة أو الضمائر (مثل: "كم سعره؟"، "متى دوامهم؟"، "ابغى احجز") بما تم ذكره سابقاً في المحادثة.';
        $lines[] = '2. استند في إجابتك حصراً ودقة إلى بيانات ومعلومات ومستندات المنشأة المرفقة أدناه، ولا تخترع أسعاراً أو تفاصيل غير موجودة.';
        $lines[] = '3. لا تكرر الترحيب أو بيانات الموقع ورقم الهاتف في المحادثات المستمرة إلا إذا سأل العميل عنها أو عند تأكيد طلب/حجز.';
        $lines[] = '4. إذا كانت معلومات العميل ناقصة أو غير محددة، وجّهه بسؤال واحد واضح ومحدد في نهاية الرد بدلاً من طرح عدة أسئلة متفرقة.';
        $lines[] = '5. إذا سأل عن ميزانية أو صنف غير متوفر بذاته، وضّح له بلطف الخيارات والبدائل الأقرب المتاحة في البيانات.';
        $lines[] = "6. {$toneInstruction} وطابق لغة العميل (عربي أو إنجليزي) واجعل الرد منسقاً بنقاط واضحة تناسب شاشة الهاتف.";
        $lines[] = '7. إذا أبدى العميل رغبة في الحجز أو الطلب، اجمع البيانات الناقصة خطوة بخطوة بوضوح (الخدمة/الصنف المطلوب، الوقت/اليوم المناسب، والاسم إن لزم) بالاعتماد على التاريخ والوقت الحالي.';
        $lines[] = '';
        $lines[] = "[بيانات ومستندات المنشأة المعتمدة / Company Knowledge Base]:\n" . $knowledgeContext;

        return implode("\n", $lines);
    }

    /**
     * Build sanitized alternating Gemini contents array from conversation history + current message.
     */
    protected function buildGeminiContents(?Collection $history, string $userMessage): array
    {
        $contents = [];

        if ($history) {
            foreach ($history as $msg) {
                $text = trim((string) $msg->message);
                if ($text === '') {
                    continue;
                }
                $role = $msg->sender === 'customer' ? 'user' : 'model';

                // Gemini requires the first turn in contents to be 'user'
                if (empty($contents) && $role !== 'user') {
                    continue;
                }

                $lastIdx = count($contents) - 1;
                if ($lastIdx >= 0 && $contents[$lastIdx]['role'] === $role) {
                    $contents[$lastIdx]['parts'][0]['text'] .= "\n" . $text;
                } else {
                    $contents[] = [
                        'role' => $role,
                        'parts' => [['text' => $text]],
                    ];
                }
            }
        }

        $lastIdx = count($contents) - 1;
        if ($lastIdx >= 0 && $contents[$lastIdx]['role'] === 'user') {
            $contents[$lastIdx]['parts'][0]['text'] .= "\n" . $userMessage;
        } else {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $userMessage]],
            ];
        }

        return $contents;
    }

    /**
     * Build OpenAI chat completion messages array from system prompt, history, and current message.
     */
    protected function buildOpenAiMessages(string $systemPrompt, ?Collection $history, string $userMessage): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        if ($history) {
            foreach ($history as $msg) {
                $text = trim((string) $msg->message);
                if ($text === '') {
                    continue;
                }
                $messages[] = [
                    'role' => $msg->sender === 'customer' ? 'user' : 'assistant',
                    'content' => $text,
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    /**
     * Generate AI answer using Gemini / OpenAI or context-aware data-driven fallback.
     *
     * @return array{reply: string, engine: string, meta: array}
     */
    protected function generateResponse(
        Company $company,
        string $userMessage,
        string $knowledgeContext,
        Conversation $conversation,
        ?Collection $history = null,
        bool $isSessionStart = true
    ): array {
        $geminiKey = $this->resolveApiKey('GEMINI_API_KEY', $company->settings['gemini_api_key'] ?? null);
        $openaiKey = $this->resolveApiKey('OPENAI_API_KEY', $company->settings['openai_api_key'] ?? null);

        $systemPrompt = $this->buildSystemPrompt($company, $knowledgeContext, $conversation, $isSessionStart);

        // 1. Try Google Gemini API (native multi-turn + systemInstruction)
        if ($geminiKey) {
            $configuredModel = $company->settings['gemini_model'] ?? 'models/gemini-2.5-flash';
            if (!str_starts_with($configuredModel, 'models/')) {
                $configuredModel = 'models/' . $configuredModel;
            }

            $modelsToTry = array_values(array_unique([
                $configuredModel,
                'models/gemini-2.5-flash',
                'models/gemini-2.0-flash',
                'models/gemini-flash-lite-latest',
            ]));

            $geminiContents = $this->buildGeminiContents($history, $userMessage);

            foreach ($modelsToTry as $currentModel) {
                try {
                    $response = Http::timeout(15.0)
                        ->connectTimeout(4.0)
                        ->post("https://generativelanguage.googleapis.com/v1beta/{$currentModel}:generateContent?key={$geminiKey}", [
                            'systemInstruction' => [
                                'parts' => [
                                    ['text' => $systemPrompt],
                                ],
                            ],
                            'contents' => $geminiContents,
                            'generationConfig' => [
                                'temperature' => 0.35,
                                'maxOutputTokens' => 900,
                            ],
                        ]);

                    if ($response->successful()) {
                        $json = $response->json();
                        $parts = $json['candidates'][0]['content']['parts'] ?? [];
                        $aiText = '';
                        foreach ($parts as $p) {
                            if (empty($p['thought']) && !empty($p['text'])) {
                                $aiText .= $p['text'];
                            }
                        }
                        if (empty($aiText) && !empty($parts)) {
                            $lastPart = end($parts);
                            $aiText = $lastPart['text'] ?? '';
                        }
                        if (!empty($aiText)) {
                            return [
                                'reply' => trim($aiText),
                                'engine' => 'gemini',
                                'meta' => ['model' => $currentModel],
                            ];
                        }
                    } else {
                        Log::warning("Gemini model {$currentModel} returned status " . $response->status());
                    }
                } catch (\Exception $e) {
                    Log::warning("Gemini timeout/error on {$currentModel}: " . $e->getMessage());
                }
            }
        }

        // 2. Try OpenAI API if configured (multi-turn failover)
        if ($openaiKey) {
            try {
                $response = Http::timeout(12.0)
                    ->connectTimeout(4.0)
                    ->withToken($openaiKey)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => 'gpt-4o-mini',
                        'messages' => $this->buildOpenAiMessages($systemPrompt, $history, $userMessage),
                        'temperature' => 0.35,
                        'max_tokens' => 800,
                    ]);

                if ($response->successful()) {
                    $aiText = $response->json('choices.0.message.content');
                    if (!empty($aiText)) {
                        return [
                            'reply' => trim($aiText),
                            'engine' => 'openai',
                            'meta' => ['model' => 'gpt-4o-mini'],
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('OpenAI API call failed: ' . $e->getMessage());
            }
        }

        // 3. Context-Aware Universal Dynamic Database RAG Fallback Engine
        $ragResult = $this->universalDatabaseRAG($company, $userMessage, $conversation, $isSessionStart, $history);

        return [
            'reply' => $ragResult['reply'],
            'engine' => 'rag_fallback',
            'meta' => $ragResult['meta'] ?? [],
        ];
    }

    /**
     * Context-Aware Universal Dynamic Database RAG Fallback Engine.
     * Bases all answers solely on the company's DB records, uploaded files, and prior conversation context.
     *
     * @return array{reply: string, meta: array}
     */
    protected function universalDatabaseRAG(
        Company $company,
        string $userMessage,
        Conversation $conversation,
        bool $isSessionStart = true,
        ?Collection $history = null
    ): array {
        $rawMsg = trim($userMessage);
        $msg = mb_strtolower($rawMsg);
        $normMsg = $this->normalizeArabic($msg);

        $isEnglish = preg_match('/[a-zA-Z]/', $msg) && !preg_match('/[\x{0600}-\x{06FF}]/u', $msg);
        $documents = $company->documents()->where('status', 'ready')->get();
        $contactNum = $company->phone ?: $company->whatsapp_number;
        $location = $company->address ?: 'المقر الرئيسي';
        $contextData = is_array($conversation->context_data) ? $conversation->context_data : [];

        // 1. Pure Greetings
        if (mb_strlen($rawMsg) < 25 && (
            preg_match('/^(مرحبا|السلام عليكم|سلام عليكم|هلا|صباح الخير|مساء الخير|اهلين|سلام|الو|ألو|مساء الورد|يا هلا)$/u', $normMsg) ||
            ($isEnglish && preg_match('/^(hi|hello|hey|good morning|good evening)$/i', $rawMsg))
        )) {
            $configuredGreeting = trim((string) ($company->settings['greeting_message'] ?? ''));
            if ($isSessionStart) {
                $greeting = $configuredGreeting !== '' ? $configuredGreeting : "أهلاً وسهلاً بك في {$company->name}! يسعدنا خدمتك.";
                $hasQuestion = str_contains($greeting, '؟') || str_contains($greeting, '?');
                $suffix = $hasQuestion
                    ? ' تفضل بطلبك أو استفسارك وسأجيبك فوراً.'
                    : ' كيف يمكننا مساعدتك اليوم؟ تفضل بطلبك أو استفسارك وسأجيبك فوراً.';
                return [
                    'reply' => $greeting . $suffix,
                    'meta' => ['intent' => 'greeting'],
                ];
            }

            return [
                'reply' => 'أهلاً بك من جديد! تفضل بسؤالك أو الخدمة التي تبحث عنها وسأفيدك مباشرة.',
                'meta' => ['intent' => 'greeting'],
            ];
        }

        // Run document search early so specific inquiries matching PDF/Excel items take precedence over generic company fields
        $matchedRows = $documents->count() > 0 ? $this->searchDynamicDocumentRows($documents, $normMsg) : [];
        $hasStrongDocumentMatch = !empty($matchedRows) && (($matchedRows[0]['_score'] ?? 0) >= 2);

        // 2. Context-aware Pronoun & Follow-up Resolution (ongoing turns without a strong new topic)
        if (!$hasStrongDocumentMatch && (!empty($contextData['last_matched_row']) || !empty($contextData['last_matched_title']) || !empty($contextData['last_topic']))) {
            $rememberedRow = is_array($contextData['last_matched_row'] ?? null) ? $contextData['last_matched_row'] : null;
            $rememberedTitle = trim((string) ($contextData['last_matched_title'] ?? ($contextData['last_topic'] ?? '')));

            $isPriceFollowUp = (bool) preg_match('/^(كم|بكم|سعره|سعرها|السعر|الكشف|كم سعره|كم سعرها|كم السعر|كم الكشف|كم التكلفه|التكلفه|كم يكلف)\b/u', $normMsg)
                || ($isEnglish && preg_match('/\b(how much|price|cost|fee)\b/i', $rawMsg));

            $isDetailFollowUp = (bool) preg_match('/(تفاصيله|تفاصيلها|تفاصيل اكثر|مواصفاته|مواصفاتها|معلومات اكثر|دوامه|دوامهم|اوقاته|اوقاتها|مواعيده|متي دوامه|موجود|متوفر|عندكم منه)/u', $normMsg)
                || ($isEnglish && preg_match('/\b(details|more info|available|schedule|hours for it)\b/i', $rawMsg));

            $isBookingIntent = (bool) preg_match('/(احجز|حجز|موعد|ابغي احجز|ابي احجز|اريد احجز|بكره|غدا|اليوم|سجلني|نحجز)/u', $normMsg)
                || ($isEnglish && preg_match('/\b(book|appointment|reserve|schedule)\b/i', $rawMsg));

            $isAlternativeFollowUp = (bool) preg_match('/(غيره|غيرها|بديل|ثاني|خيار ثاني|اقل سعر|ارخص)/u', $normMsg);

            if ($isBookingIntent && $rememberedTitle !== '') {
                $bookingReply = "بكل سرور! بخصوص حجز موعد لـ **{$rememberedTitle}**:\n"
                    . "• أرسل لي **اليوم والوقت المناسب لك** مع **الاسم الكريم** لتنسيق الموعد لك مباشرة.";
                if ($contactNum) {
                    $bookingReply .= "\n• أو للتنسيق السريع هاتفياً: {$contactNum}";
                }
                return [
                    'reply' => $bookingReply,
                    'meta' => [
                        'intent' => 'booking_interest',
                        'matched_row' => $rememberedRow,
                        'matched_title' => $rememberedTitle,
                    ],
                ];
            }

            if ($isPriceFollowUp && $rememberedRow) {
                $priceReply = $this->formatFollowUpPriceReply($rememberedRow, $rememberedTitle);
                if ($priceReply !== null) {
                    return [
                        'reply' => $priceReply,
                        'meta' => [
                            'intent' => 'price_followup',
                            'matched_row' => $rememberedRow,
                            'matched_title' => $rememberedTitle,
                        ],
                    ];
                }
            }

            if (($isPriceFollowUp || $isDetailFollowUp) && $rememberedRow) {
                $details = $this->formatDynamicRowDetails($rememberedRow, $company);
                return [
                    'reply' => $details . "\n\nهل تود الاستفسار عن خيار آخر أو حجز موعد؟",
                    'meta' => [
                        'intent' => 'details_followup',
                        'matched_row' => $rememberedRow,
                        'matched_title' => $rememberedTitle,
                    ],
                ];
            }

            if ($isAlternativeFollowUp && $documents->count() > 0 && $rememberedTitle !== '') {
                $altQuery = $this->normalizeArabic($rememberedTitle);
                $altRows = $this->searchDynamicDocumentRows($documents, $altQuery);
                $filteredAlt = array_values(array_filter(
                    $altRows,
                    fn ($r) => $this->extractRowTitle($r) !== $rememberedTitle
                ));
                if (!empty($filteredAlt)) {
                    return [
                        'reply' => $this->formatDynamicSummaryList($filteredAlt, $company, $isSessionStart),
                        'meta' => [
                            'intent' => 'alternative_followup',
                            'matched_row' => $filteredAlt[0],
                            'matched_title' => $this->extractRowTitle($filteredAlt[0]),
                        ],
                    ];
                }
            }

            // Combined search with remembered topic if message had extra keywords
            if ($documents->count() > 0 && $rememberedTitle !== '' && mb_strlen($normMsg) > 3) {
                $combinedRows = $this->searchDynamicDocumentRows($documents, trim($normMsg . ' ' . $this->normalizeArabic($rememberedTitle)));
                if (!empty($combinedRows)) {
                    $matchedRows = $combinedRows;
                    $hasStrongDocumentMatch = ($matchedRows[0]['_score'] ?? 0) >= 2;
                }
            }
        }

        // 3. Location / Address Inquiry (only when not targeting a specific document section)
        if (!$hasStrongDocumentMatch && (
            preg_match('/(موقع|وين|عنوان|مكانكم|شارع|فرع|خريطه|خريطة|لوكيشن)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(location|where|address|place|map)\b/i', $rawMsg))
        )) {
            $reply = "📍 موقعنا في {$company->name}:\n" . ($company->address ?: "تفضل بزيارتنا في مقرنا الرئيسي.");
            if ($company->google_maps_url) {
                $reply .= "\n🗺 رابط خرائط جوجل: " . $company->google_maps_url;
            }
            if ($contactNum && $isSessionStart) {
                $reply .= "\n📞 هاتف التواصل: {$contactNum}";
            }
            return [
                'reply' => $reply,
                'meta' => ['intent' => 'location'],
            ];
        }

        // 4. Working Hours Inquiry (only when not targeting a specific document section)
        if (!$hasStrongDocumentMatch && (
            preg_match('/(دوام|وقت|ساعات|تفتحون|تغلقون|ايام|متى)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(hours|working hours|open|time|schedule)\b/i', $rawMsg))
        )) {
            $hoursText = $company->working_hours ?: "من 8 صباحاً حتى 10 مساءً، ويسعدنا دائماً استقبالكم.";
            $reply = $isSessionStart
                ? "⏰ أوقات العمل في {$company->name}:\n{$hoursText}\n📞 هاتف: {$contactNum}"
                : "⏰ أوقات العمل لدينا:\n{$hoursText}\n\nهل ترغب في حجز موعد أو الاستفسار عن خدمة محددة؟";

            return [
                'reply' => $reply,
                'meta' => ['intent' => 'working_hours'],
            ];
        }

        // 5. Contact / Support Inquiry (only when not targeting a specific document section)
        if (!$hasStrongDocumentMatch && (
            preg_match('/(رقم|اتصال|تواصل|هاتف|جوال|ايميل|دعم|خدمة عملاء|مسؤول)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(contact|phone|call|email|whatsapp|number|support)\b/i', $rawMsg))
        )) {
            $reply = "📞 معلومات التواصل مع منشأة {$company->name}:\n";
            if ($company->phone) $reply .= "• هاتف: {$company->phone}\n";
            if ($company->whatsapp_number) $reply .= "• واتساب: {$company->whatsapp_number}\n";
            if ($company->support_phone) $reply .= "• مسؤول المبيعات المباشر: {$company->support_phone}\n";
            if ($company->address) $reply .= "📍 الموقع: {$company->address}";
            return [
                'reply' => trim($reply),
                'meta' => ['intent' => 'contact'],
            ];
        }

        // 6. About Us / What do you offer (only if no document rows match)
        if (!$hasStrongDocumentMatch && (
            preg_match('/(من انتم|من أنتم|نبذه|نبذة|عنكم|عن المنشاه|عن المنشأة|عن الشركه|عن الشركة|نشاطكم|مشروعكم|ماهو مشروعكم|ما هو مشروعكم)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(who are you|about|about us|company|services)\b/i', $rawMsg))
        )) {
            $reply = "🏢 نبذة عن {$company->name}:\n";
            $reply .= ($company->about_us ?: "نحن نقدم أفضل المنتجات والخدمات ونهتم بتوفير أعلى معايير الجودة والأسعار لعملائنا.");
            if ($company->services_offered) {
                $reply .= "\n\n💡 أبرز ما نقدمه:\n" . $company->services_offered;
            }
            if ($contactNum) $reply .= "\n\n📞 للتواصل والاستفسار: {$contactNum}";
            return [
                'reply' => $reply,
                'meta' => ['intent' => 'about_us'],
            ];
        }

        // 7. Booking Intent without prior matched row
        if (
            preg_match('/(احجز|حجز|موعد|ابغي احجز|ابي احجز|اريد احجز)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(book|appointment|reserve)\b/i', $rawMsg))
        ) {
            $bookingAsk = $isSessionStart
                ? "أهلاً بك في **{$company->name}**! يسعدنا تنسيق موعدك.\n"
                : "بكل سرور، يسعدنا تنسيق موعدك.\n";

            if (!empty($matchedRows)) {
                $topTitle = $this->extractRowTitle($matchedRows[0]);
                $bookingAsk .= "بخصوص **{$topTitle}**، أرسل لي **اليوم والوقت المناسب لك** مع **الاسم الكريم** لإتمام التنسيق.";
                return [
                    'reply' => $bookingAsk,
                    'meta' => [
                        'intent' => 'booking_interest',
                        'matched_row' => $matchedRows[0],
                        'matched_title' => $topTitle,
                    ],
                ];
            }

            $bookingAsk .= "ما هي **الخدمة أو القسم المطلوب**، وما **اليوم والوقت المناسب لك**؟";
            return [
                'reply' => $bookingAsk,
                'meta' => ['intent' => 'booking_interest'],
            ];
        }

        // 8. Dynamic Budget / Numeric Inquiry
        $userBudget = $this->extractBudgetFromMessage($normMsg);
        if ($userBudget !== null && $documents->count() > 0) {
            $budgetReply = $this->evaluateDynamicBudgetAgainstData($userBudget, $documents, $company, $isSessionStart);
            if ($budgetReply) {
                return [
                    'reply' => $budgetReply,
                    'meta' => [
                        'intent' => 'budget_inquiry',
                        'budget' => $userBudget,
                    ],
                ];
            }
        }

        // 9. Universal Semantic Document Search
        if (!empty($matchedRows)) {
            $topRow = $matchedRows[0];
            $topTitle = $this->extractRowTitle($topRow);

            if (count($matchedRows) <= 3) {
                $replies = [];
                foreach ($matchedRows as $row) {
                    $replies[] = $this->formatDynamicRowDetails($row, $company);
                }
                if ($isSessionStart) {
                    $callToAction = "\n📞 للتواصل المباشر والطلب: {$contactNum}";
                    if ($location) $callToAction .= "\n📍 الموقع: {$location}";
                } else {
                    $callToAction = "\nهل تود معرفة تفاصيل إضافية أو حجز موعد لهذه الخدمة؟";
                }
                return [
                    'reply' => implode("\n\n───────────────\n\n", $replies) . $callToAction,
                    'meta' => [
                        'intent' => 'item_inquiry',
                        'matched_row' => $topRow,
                        'matched_title' => $topTitle,
                    ],
                ];
            }

            return [
                'reply' => $this->formatDynamicSummaryList($matchedRows, $company, $isSessionStart),
                'meta' => [
                    'intent' => 'item_inquiry',
                    'matched_row' => $topRow,
                    'matched_title' => $topTitle,
                ],
            ];
        }

        // 10. General Catalog / Offerings Request
        $isCatalogIntent = preg_match('/(قائمه|قائمة|كتالوج|منتجات|اصناف|أصناف|بضاعه|بضاعة|متوفر|موجود|ايش عندكم|شو عندكم|ماذا لديكم|ما لديكم|اعرض|ورني|عطني|اسعار|اسعاركم|عيادات|عياده|عيادة|اقسام|أقسام|تخصصات|خدمات|خدماتكم|اطباء|أطباء|دكاتره|دكاترة)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(list|catalog|catalogue|inventory|available|products|items|show me|prices|clinics|departments|services|doctors)\b/i', $rawMsg));

        if ($isCatalogIntent && $documents->count() > 0) {
            $catalogResponse = $this->formatDynamicCatalogOverview($documents, $company, $isSessionStart);
            if ($catalogResponse) {
                return [
                    'reply' => $catalogResponse,
                    'meta' => ['intent' => 'catalog'],
                ];
            }
        }

        // 11. Universal Fallback
        if ($documents->count() > 0) {
            $catalogOverview = $this->formatDynamicCatalogOverview($documents, $company, $isSessionStart);
            if ($catalogOverview) {
                $prefix = $isSessionStart
                    ? "أهلاً بك في **{$company->name}**! يسعدنا خدمتك والرد على استفسارك.\n\n"
                    : "إليك أبرز الخيارات المتاحة لدينا — أخبرني باسم الصنف أو الخدمة التي تقصدها لأفيدك مباشرة:\n\n";
                return [
                    'reply' => $prefix . $catalogOverview,
                    'meta' => ['intent' => 'fallback_catalog'],
                ];
            }
        }

        if ($isSessionStart) {
            $defaultReply = "أهلاً بك في **{$company->name}**! يسعدنا تواصلك.\n";
            if ($company->about_us) $defaultReply .= "{$company->about_us}\n";
            if ($company->services_offered) $defaultReply .= "💡 خدماتنا: {$company->services_offered}\n";
            if ($contactNum) $defaultReply .= "📞 للتواصل المباشر: {$contactNum}\n";
            if ($company->address) $defaultReply .= "📍 الموقع: {$company->address}";

            return [
                'reply' => trim($defaultReply),
                'meta' => ['intent' => 'fallback_default'],
            ];
        }

        $ongoingFallback = 'لم ألتقط النقطة المطلوبة بدقة — هل يمكنك توضيح اسم الخدمة أو الصنف الذي تسأل عنه؟';
        if ($contactNum) {
            $ongoingFallback .= "\nوإذا رغبت بالتواصل مع فريقنا مباشرة: {$contactNum}";
        }

        return [
            'reply' => $ongoingFallback,
            'meta' => ['intent' => 'fallback_clarify'],
        ];
    }

    /**
     * Format a focused price follow-up answer for a previously remembered document row.
     */
    protected function formatFollowUpPriceReply(array $row, string $title): ?string
    {
        $cleanRow = $row;
        unset($cleanRow['_score'], $cleanRow['_num_price']);

        $priceLines = [];
        foreach ($cleanRow as $colName => $colValue) {
            $colNameStr = trim((string) $colName);
            $colValStr = trim((string) $colValue);
            if ($colValStr === '') {
                continue;
            }
            if ($this->isPriceColumn($colNameStr)) {
                $formatted = is_numeric(str_replace(',', '', $colValStr))
                    ? number_format((float) str_replace(',', '', $colValStr))
                    : $colValStr;
                $priceLines[] = "💰 **{$colNameStr}:** {$formatted} ريال";
            }
        }

        if (empty($priceLines)) {
            return null;
        }

        $header = $title !== '' ? "بخصوص **{$title}**:" : 'بخصوص البند السابق:';
        return $header . "\n" . implode("\n", $priceLines) . "\n\nهل ترغب في حجز موعد أو الاستفسار عن تفاصيل أخرى؟";
    }

    /**
     * Extract clean display title from a document row.
     */
    protected function extractRowTitle(array $row): string
    {
        foreach ($row as $k => $v) {
            if (in_array((string) $k, ['_score', '_num_price'], true)) {
                continue;
            }
            $val = trim((string) $v);
            if ($val !== '' && !is_numeric($val) && !$this->isPriceColumn((string) $k)) {
                return $val;
            }
        }

        foreach ($row as $k => $v) {
            if (in_array((string) $k, ['_score', '_num_price'], true)) {
                continue;
            }
            $val = trim((string) $v);
            if ($val !== '') {
                return $val;
            }
        }

        return '';
    }

    /**
     * Update conversation state, context_data memory, and compact rolling summary after every turn.
     */
    protected function updateConversationContext(
        Conversation $conversation,
        Company $company,
        string $incomingMessage,
        string $reply,
        string $engine,
        array $meta
    ): string {
        $contextData = is_array($conversation->context_data) ? $conversation->context_data : [];
        $normMsg = $this->normalizeArabic($incomingMessage);
        $documents = $company->documents()->where('status', 'ready')->get();

        // Ensure we track matched item & budget even when Gemini/OpenAI handled the turn
        $matchedTitle = $meta['matched_title'] ?? null;
        $matchedRow = $meta['matched_row'] ?? null;
        if (!$matchedRow && $documents->count() > 0) {
            $rows = $this->searchDynamicDocumentRows($documents, $normMsg);
            if (!empty($rows) && ($rows[0]['_score'] ?? 0) >= 2) {
                $matchedRow = $rows[0];
                $matchedTitle = $this->extractRowTitle($matchedRow);
            }
        }

        $budget = $meta['budget'] ?? $this->extractBudgetFromMessage($normMsg);

        $intent = $meta['intent'] ?? null;
        if (!$intent) {
            if (preg_match('/(احجز|حجز|موعد|ابغي احجز|ابي احجز|اريد احجز|\bbook\b|\bappointment\b)/ui', $normMsg . ' ' . $incomingMessage)) {
                $intent = 'booking_interest';
            } elseif ($matchedTitle || $budget !== null || preg_match('/(سعر|بكم|كم|تفاصيل|عرض|دوام|موقع)/u', $normMsg)) {
                $intent = 'inquiring';
            } else {
                $intent = 'browsing';
            }
        }

        if ($matchedRow) {
            unset($matchedRow['_score'], $matchedRow['_num_price']);
            $contextData['last_matched_row'] = $matchedRow;
        }
        if ($matchedTitle) {
            $contextData['last_matched_title'] = $matchedTitle;
            $contextData['last_topic'] = $matchedTitle;

            $recentTopics = is_array($contextData['recent_topics'] ?? null) ? $contextData['recent_topics'] : [];
            $recentTopics = array_values(array_filter($recentTopics, fn ($t) => $t !== $matchedTitle));
            $recentTopics[] = $matchedTitle;
            $contextData['recent_topics'] = array_slice($recentTopics, -5);
        }
        if ($budget !== null) {
            $contextData['mentioned_budget'] = $budget;
        }

        $contextData['last_intent'] = $intent;
        $contextData['last_engine'] = $engine;
        $turnsCount = ((int) ($contextData['turns_count'] ?? 0)) + 1;
        $contextData['turns_count'] = $turnsCount;

        // Determine updated conversation_state (preserve human_handoff unless explicitly reset)
        $currentState = $conversation->conversation_state ?: 'browsing';
        if ($currentState === 'human_handoff') {
            $newState = 'human_handoff';
        } elseif ($intent === 'booking_interest' || $currentState === 'booking') {
            $newState = 'booking';
        } elseif (in_array($intent, ['item_inquiry', 'price_followup', 'details_followup', 'alternative_followup', 'budget_inquiry', 'inquiring'], true)) {
            $newState = 'inquiring';
        } elseif ($currentState === 'inquiring' && !in_array($intent, ['greeting'], true)) {
            $newState = 'inquiring';
        } else {
            $newState = 'browsing';
        }

        // Maintain compact rolling Arabic summary (<= 500 chars)
        $summary = $conversation->summary;
        $shouldUpdateSummary = $turnsCount >= 4
            || $matchedTitle !== null
            || $budget !== null
            || $newState === 'booking';

        if ($shouldUpdateSummary) {
            $summaryParts = [];
            if (!empty($contextData['recent_topics'])) {
                $summaryParts[] = 'المواضيع: ' . implode('، ', $contextData['recent_topics']);
            }
            if (!empty($contextData['mentioned_budget'])) {
                $summaryParts[] = 'الميزانية: ' . number_format((float) $contextData['mentioned_budget']) . ' ريال';
            }
            $stateArabic = match ($newState) {
                'booking' => 'طلب حجز موعد',
                'inquiring' => 'استفسار عن تفاصيل/أسعار',
                'human_handoff' => 'محول لموظف',
                default => 'تصفح عام',
            };
            $summaryParts[] = "المرحلة: {$stateArabic}";
            $summaryParts[] = 'آخر سؤال: ' . mb_substr(trim($incomingMessage), 0, 120);

            $summary = mb_substr(implode(' | ', $summaryParts), 0, 500);
        }

        $conversation->update([
            'conversation_state' => $newState,
            'context_data' => $contextData,
            'summary' => $summary,
        ]);

        return $newState;
    }

    /**
     * Search any document's extracted rows or text sections for matching query tokens (domain-agnostic).
     */
    protected function searchDynamicDocumentRows($documents, string $normQuery): array
    {
        $matches = [];
        $words = preg_split('/\s+/u', $normQuery);

        // Filter out stop words that don't indicate a product, clinic, or feature
        $stopWords = [
            'في', 'من', 'ما', 'هي', 'هو', 'هم', 'شو', 'ايش', 'وش', 'هل', 'عن', 'عندكم', 'لديكم',
            'عندك', 'موجود', 'موجوده', 'متوفر', 'متوفره', 'المتوفره', 'الموجوده', 'اريد', 'ابغى',
            'ابغي', 'ابي', 'احتاج', 'كم', 'سعر', 'السعر', 'سعره', 'سعرها', 'اسعار', 'بكم', 'الكشف',
            'تكلفه', 'التكلفه', 'فقط', 'معي', 'عندي', 'ممكن', 'احصل',
            'علا', 'على', 'الي', 'اللي', 'التي', 'الذي', 'التفاصيل', 'تفاصيل', 'تفاصيله', 'تفاصيلها',
            'مواصفاته', 'مواصفاتها', 'معلومات', 'بخصوص', 'اكثر',
            'وين', 'اين', 'مكان', 'موقع', 'رقم', 'واتساب', 'واتس', 'تواصل', 'دكتور', 'طبيب', 'اسم',
            'دوامه', 'دوامهم', 'اوقاته', 'اوقاتها', 'مواعيده', 'احجز', 'حجز', 'موعد', 'غيره', 'غيرها', 'بديل', 'ثاني'
        ];
        $filteredWords = array_values(array_diff($words, $stopWords));

        if (empty($filteredWords)) {
            return [];
        }

        foreach ($documents as $doc) {
            $docHasRowMatch = false;
            $dataRows = is_array($doc->extracted_data) && array_is_list($doc->extracted_data)
                ? $doc->extracted_data
                : [];

            foreach ($dataRows as $row) {
                if (!is_array($row) || empty(array_filter($row))) {
                    continue;
                }

                $combinedText = implode(' ', array_merge(array_keys($row), array_values($row)));
                $rowText = $this->normalizeArabic($combinedText);
                $score = $this->scoreTextAgainstQueryWords($rowText, $filteredWords);

                if ($score > 0) {
                    $row['_score'] = $score;
                    $matches[] = $row;
                    $docHasRowMatch = true;
                }
            }

            // Fallback: search raw extracted_text paragraphs if no structured rows matched
            if (!$docHasRowMatch && !empty($doc->extracted_text)) {
                $blocks = preg_split("/\n{2,}/u", (string) $doc->extracted_text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($blocks as $block) {
                    $block = trim($block);
                    if ($block === '' || str_starts_with($block, '### محتوى ملف')) {
                        continue;
                    }

                    $normBlock = $this->normalizeArabic($block);
                    $score = $this->scoreTextAgainstQueryWords($normBlock, $filteredWords);
                    if ($score > 0) {
                        $lines = array_values(array_filter(array_map('trim', explode("\n", $block))));
                        $heading = $lines[0] ?? 'تفاصيل المستند';
                        $details = count($lines) > 1 ? implode("\n", array_slice($lines, 1)) : $block;
                        $matches[] = [
                            'القسم / البند' => $heading,
                            'التفاصيل' => $details,
                            '_score' => $score,
                        ];
                    }
                }
            }
        }

        // Sort by highest keyword score
        usort($matches, fn ($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));

        return $matches;
    }

    /**
     * Score normalized text against filtered normalized query words using Arabic stemming.
     */
    protected function scoreTextAgainstQueryWords(string $normText, array $filteredWords): int
    {
        $score = 0;

        foreach ($filteredWords as $w) {
            $w = trim($w);
            if (mb_strlen($w) < 2) {
                continue;
            }

            $candidates = $this->extractArabicStems($w);
            foreach ($candidates as $idx => $candidate) {
                if (mb_strlen($candidate) >= 2 && mb_strpos($normText, $candidate) !== false) {
                    // Exact match gets slightly higher weight than stripped stem match
                    $score += ($idx === 0) ? 3 : 2;
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * Generate normalized Arabic search stems for prefixes, suffixes, and common broken plurals.
     */
    protected function extractArabicStems(string $word): array
    {
        $stems = [$word];

        $pluralMap = [
            'عيادات' => ['عياد', 'عياده'],
            'العيادات' => ['عياد', 'عياده'],
            'عياده' => ['عياد'],
            'العياده' => ['عياد'],
            'اطباء' => ['طبيب', 'د.'],
            'الاطباء' => ['طبيب', 'د.'],
            'دكاتره' => ['طبيب', 'دكتور', 'د.'],
            'اقسام' => ['قسم', 'عياد'],
            'الاقسام' => ['قسم', 'عياد'],
            'اسعار' => ['سعر'],
            'الاسعار' => ['سعر'],
            'مواعيد' => ['موعد', 'وقت', 'دوام'],
            'المواعيد' => ['موعد', 'وقت', 'دوام'],
            'تخصصات' => ['تخصص', 'عياد'],
            'التخصصات' => ['تخصص', 'عياد'],
        ];

        if (isset($pluralMap[$word])) {
            foreach ($pluralMap[$word] as $mapped) {
                $stems[] = $mapped;
            }
        }

        // Strip common leading prefixes (ال, وال, بال, لل, كال, و, ب, ل)
        $prefixes = ['وال', 'بال', 'كال', 'لل', 'ال', 'و', 'ب', 'ل'];
        $strippedList = [$word];
        foreach ($prefixes as $prefix) {
            $pLen = mb_strlen($prefix);
            if (mb_strlen($word) > $pLen + 2 && mb_substr($word, 0, $pLen) === $prefix) {
                $sub = mb_substr($word, $pLen);
                $stems[] = $sub;
                $strippedList[] = $sub;
            }
        }

        // Strip plural / feminine suffixes (ات, ين, ون, ه) when resulting stem >= 3 chars
        foreach ($strippedList as $base) {
            foreach (['ات', 'ين', 'ون'] as $suffix) {
                if (mb_strlen($base) >= 5 && mb_substr($base, -2) === $suffix) {
                    $stems[] = mb_substr($base, 0, -2);
                }
            }
            if (mb_strlen($base) >= 4 && mb_substr($base, -1) === 'ه') {
                $stems[] = mb_substr($base, 0, -1);
            }
        }

        return array_values(array_unique($stems));
    }

    /**
     * Format a single document row dynamically using its actual column keys.
     */
    protected function formatDynamicRowDetails(array $row, Company $company): string
    {
        unset($row['_score'], $row['_num_price']);
        $lines = [];

        // Determine title: first non-empty text column
        $title = '';
        $titleKey = null;
        foreach ($row as $k => $v) {
            if (!empty(trim((string) $v)) && !is_numeric($v)) {
                $title = trim((string) $v);
                $titleKey = (string) $k;
                break;
            }
        }

        $lines[] = "✨ **" . ($title ?: 'تفاصيل الصنف / الخدمة') . "**";

        foreach ($row as $colName => $colValue) {
            $colName = trim((string) $colName);
            $colValue = trim((string) $colValue);
            if (empty($colValue)) continue;

            // Avoid printing the heading twice when it's already used as title
            if ($colName === $titleKey && $colValue === $title) {
                continue;
            }

            // Detect price columns
            if ($this->isPriceColumn($colName)) {
                $formatted = is_numeric(str_replace(',', '', $colValue)) ? number_format((float) str_replace(',', '', $colValue)) : $colValue;
                $lines[] = "💰 **{$colName}:** {$formatted} ريال";
            } elseif ($colName === 'التفاصيل') {
                $lines[] = $colValue;
            } else {
                $lines[] = "• **{$colName}:** {$colValue}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format a dynamic summary list of multiple matching rows.
     */
    protected function formatDynamicSummaryList(array $rows, Company $company, bool $isSessionStart = true): string
    {
        $header = $isSessionStart
            ? "📋 **الخيارات والمعلومات المطابقة لطلبك لدى {$company->name}:**\n"
            : "📋 **الخيارات المطابقة:**\n";
        $lines = [$header];
        $i = 1;

        foreach (array_slice($rows, 0, 12) as $row) {
            unset($row['_score'], $row['_num_price']);
            $firstCol = '';
            $detailCol = '';
            $priceCol = '';

            foreach ($row as $k => $v) {
                $v = trim((string) $v);
                if (empty($v)) continue;

                if (empty($firstCol)) {
                    $firstCol = $v;
                } elseif ($k === 'التفاصيل' && $v !== $firstCol) {
                    $detailCol = "\n   " . str_replace("\n", "\n   ", $v);
                } elseif ($this->isPriceColumn((string) $k)) {
                    $formatted = is_numeric(str_replace(',', '', $v)) ? number_format((float) str_replace(',', '', $v)) : $v;
                    $priceCol = " — **{$formatted} ريال**";
                }
            }

            if ($detailCol !== '') {
                $lines[] = "🔹 **{$firstCol}**{$priceCol}{$detailCol}";
            } else {
                $lines[] = "{$i}. **{$firstCol}**{$priceCol}";
                $i++;
            }
        }

        if ($isSessionStart) {
            $contactNum = $company->phone ?: $company->whatsapp_number;
            $lines[] = "\n💡 اذكر اسم أي بند أو عيادة لمعرفة تفاصيلها فوراً، أو تواصل معنا على: {$contactNum}";
        } else {
            $lines[] = "\n💡 اذكر اسم البند الذي تريده وسأعطيك تفاصيله أو أنسّق لك موعداً.";
        }

        return implode("\n", $lines);
    }

    /**
     * Format dynamic catalog overview from whatever documents are stored for this company.
     */
    protected function formatDynamicCatalogOverview($documents, Company $company, bool $isSessionStart = true): ?string
    {
        $allRows = [];

        foreach ($documents as $doc) {
            if (!empty($doc->extracted_data) && is_array($doc->extracted_data) && array_is_list($doc->extracted_data)) {
                foreach ($doc->extracted_data as $row) {
                    if (is_array($row) && !empty(array_filter($row))) {
                        $allRows[] = $row;
                    }
                }
            }
        }

        if (empty($allRows)) {
            // Text document fallback
            foreach ($documents as $doc) {
                if (!empty($doc->extracted_text)) {
                    $header = $isSessionStart
                        ? "📋 **قائمة البيانات المتاحة لدى {$company->name}:**\n\n"
                        : "📋 **البيانات المتاحة:**\n\n";
                    return $header . mb_substr($doc->extracted_text, 0, 2500);
                }
            }
            return null;
        }

        $header = $isSessionStart
            ? "📋 **قائمة البيانات والخدمات المتاحة لدى {$company->name}:**\n"
            : "📋 **الخدمات والبنود المتاحة:**\n";
        $lines = [$header];
        $i = 1;
        $isPdfSections = isset($allRows[0]['القسم / البند']);

        if ($isPdfSections) {
            $charBudget = 2500;
            $currentLen = 0;
            foreach (array_slice($allRows, 0, 15) as $row) {
                $heading = trim((string) ($row['القسم / البند'] ?? ''));
                $details = trim((string) ($row['التفاصيل'] ?? ''));
                $entry = "🔹 **{$heading}**";
                if ($details !== '' && $details !== $heading) {
                    $entry .= "\n" . $details;
                }
                if ($currentLen + mb_strlen($entry) > $charBudget) {
                    break;
                }
                $lines[] = $entry;
                $currentLen += mb_strlen($entry);
            }
        } else {
            foreach (array_slice($allRows, 0, 12) as $row) {
                $labelParts = [];
                $priceStr = '';

                foreach ($row as $k => $v) {
                    $v = trim((string) $v);
                    if (empty($v)) continue;

                    if ($this->isPriceColumn((string) $k)) {
                        $formatted = is_numeric(str_replace(',', '', $v)) ? number_format((float) str_replace(',', '', $v)) : $v;
                        $priceStr = " — **{$formatted} ريال**";
                    } elseif (count($labelParts) < 2 && !is_numeric($v)) {
                        $labelParts[] = $v;
                    }
                }

                $label = implode(' ', $labelParts);
                $lines[] = "{$i}. **{$label}**{$priceStr}";
                $i++;
            }
        }

        if ($isSessionStart) {
            $contactNum = $company->phone ?: $company->whatsapp_number;
            $lines[] = "\n✨ لمعرفة تفاصيل أي عنصر أو الاستفسار عنه، يمكنك الرد باسمه أو الاتصال بنا: {$contactNum}";
        } else {
            $lines[] = "\n✨ أرسل اسم أي عنصر لأعطيك كامل تفاصيله أو أساعدك في الحجز.";
        }

        return implode("\n", $lines);
    }

    /**
     * Dynamically evaluate a customer's mentioned budget against document price columns.
     */
    protected function evaluateDynamicBudgetAgainstData(float $budget, $documents, Company $company, bool $isSessionStart = true): ?string
    {
        $rowsWithPrice = [];

        foreach ($documents as $doc) {
            if (empty($doc->extracted_data) || !is_array($doc->extracted_data)) continue;

            foreach ($doc->extracted_data as $row) {
                if (!is_array($row)) continue;

                foreach ($row as $k => $v) {
                    if ($this->isPriceColumn($k)) {
                        $numericPrice = (float)preg_replace('/[^0-9]/', '', (string)$v);
                        if ($numericPrice > 0) {
                            $row['_num_price'] = $numericPrice;
                            $rowsWithPrice[] = $row;
                            break;
                        }
                    }
                }
            }
        }

        if (empty($rowsWithPrice)) return null;

        // Sort by price ascending
        usort($rowsWithPrice, fn($a, $b) => ($a['_num_price'] <=> $b['_num_price']));

        $cheapest = $rowsWithPrice[0];
        $minPrice = $cheapest['_num_price'];
        $formattedBudget = number_format($budget);
        $contactNum = $company->phone ?: $company->whatsapp_number;
        $location = $company->address ?: 'المقر الرئيسي';

        // Case 1: Budget is less than minimum price
        if ($budget < $minPrice) {
            $cheapestTitle = '';
            foreach ($cheapest as $k => $v) {
                if (!empty(trim((string)$v)) && !is_numeric($v) && !$this->isPriceColumn($k)) {
                    $cheapestTitle = trim((string)$v);
                    break;
                }
            }
            $cheapestPriceStr = number_format($minPrice);

            $intro = $isSessionStart ? "أهلاً بك في **{$company->name}**! يسعدنا خدمتك والرد على استفسارك بدقة. 🤝\n\n" : '';
            $footer = $isSessionStart
                ? "• كما نرحب بتواصلك المباشر مع الإدارة للاستفسار عن أي عروض أو خيارات خاصة: {$contactNum}\n📍 الموقع: {$location}"
                : "• تفضل بإخباري إذا رغبت في معرفة تفاصيل هذا الخيار أو بدائل أخرى.";

            return $intro .
                "بخصوص ميزانيتك المذكورة (**{$formattedBudget} ريال**):\n" .
                "• أقل خيار متوفر لدينا يبدأ بسعر كاش (**{$cheapestPriceStr} ريال**) لـ ({$cheapestTitle}).\n\n" .
                "💡 **الخيارات المتاحة لخدمتك:**\n" .
                "• يمكنك الاستفادة من مبلغ (**{$formattedBudget} ريال**) كـ **دفعة أولى** في حال توفر نظام التقسيط أو التسهيلات الميسرة.\n" .
                $footer;
        }

        // Case 2: Budget fits items
        $matching = [];
        foreach ($rowsWithPrice as $row) {
            if ($row['_num_price'] <= $budget * 1.15) {
                $matching[] = $row;
            }
        }

        $lines = [];
        if ($isSessionStart) {
            $lines[] = "أهلاً بك في **{$company->name}**! يسعدنا تواصلك.";
        }
        $lines[] = "بخصوص ميزانيتك المحددة (**{$formattedBudget} ريال**)، إليك أنسب الخيارات المتاحة لدينا في حدود هذه الميزانية:\n";

        $i = 1;
        foreach (array_slice($matching, 0, 4) as $item) {
            $title = '';
            foreach ($item as $k => $v) {
                if (!empty(trim((string)$v)) && !is_numeric($v) && !$this->isPriceColumn($k)) {
                    $title = trim((string)$v);
                    break;
                }
            }
            $priceStr = number_format($item['_num_price']);
            $lines[] = "{$i}. **{$title}** — **{$priceStr} ريال**";
            $i++;
        }

        $lines[] = $isSessionStart
            ? "\n📞 للحجز والاستفسار المباشر: {$contactNum}\n📍 الموقع: {$location}"
            : "\nأي خيار من هذه الخيارات تود معرفة تفاصيله أو حجزه؟";

        return implode("\n", $lines);
    }

    /**
     * Check if a column name represents price/cost.
     */
    protected function isPriceColumn(string $colName): bool
    {
        $norm = $this->normalizeArabic($colName);
        return (bool)preg_match('/(سعر|قيمه|قيمة|تكلفه|تكلفة|مبلغ|price|cost|fee|amount|fee)/u', $norm);
    }

    /**
     * Extract budget/amount mentioned by customer in Arabic or English.
     */
    protected function extractBudgetFromMessage(string $normMsg): ?float
    {
        // Matches e.g. "40 الف", "40الف", "150 الف", "150k"
        if (preg_match('/([0-9]{1,4})\s*(?:الف|ألف|k)/u', $normMsg, $m)) {
            $val = (float)$m[1] * 1000;
            if ($val > 1000) return $val;
        }

        // Matches e.g. "40000", "120000"
        if (preg_match('/(?:معي|عندي|ميزانيتي|ميزانية|بحدود|حوالي|قدرتي|فلوسي|بمبلغ|ب|احصل)\s*([0-9]{4,7})/u', $normMsg, $m)) {
            $val = (float)$m[1];
            if ($val >= 5000) return $val;
        }

        return null;
    }

    /**
     * Arabic text normalizer (unifies alef forms, taa marboota, removes diacritics, converts numerals).
     */
    protected function normalizeArabic(string $text): string
    {
        $text = mb_strtolower($text);

        // Convert Eastern Arabic-Indic numerals (٠-٩) to standard (0-9)
        $easternNumerals = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $westernNumerals = ['0','1','2','3','4','5','6','7','8','9'];
        $text = str_replace($easternNumerals, $westernNumerals, $text);

        // Replace alef variants (أ, إ, آ -> ا)
        $text = preg_replace('/[إأآا]/u', 'ا', $text);
        // Replace taa marboota (ة -> ه)
        $text = preg_replace('/[ةه]/u', 'ه', $text);
        // Replace yaa variants (ى -> ي)
        $text = preg_replace('/[يى]/u', 'ي', $text);
        // Remove Arabic diacritics (harakat)
        $text = preg_replace('/[\x{064B}-\x{0652}]/u', '', $text);
        // Strip punctuation and symbols
        $text = preg_replace('/[؟\?!\.,;:\-_=\+\(\)\[\]\{\}\/\\\"\'«»]/u', ' ', $text);
        // Clean multiple spaces
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}