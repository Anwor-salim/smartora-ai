<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SystemNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    /**
     * Process an incoming message for a company from a customer.
     */
    public function handleCustomerMessage(Company $company, string $customerPhone, string $incomingMessage): array
    {
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
        $conversation = Conversation::firstOrCreate(
            [
                'company_id' => $company->id,
                'customer_phone' => $customerPhone,
            ],
            [
                'customer_name' => 'عميل واتساب (' . substr($customerPhone, -4) . ')',
                'status' => 'active',
            ]
        );

        if ($conversation->wasRecentlyCreated) {
            $isNewCustomer = true;
            SystemNotification::create([
                'company_id' => $company->id,
                'title' => 'عميل جديد يتواصل مع البوت',
                'message' => "تواصل عميل جديد برقم ($customerPhone) مع شركة ({$company->name})",
                'type' => 'new_customer',
                'data' => [
                    'phone' => $customerPhone,
                    'company_id' => $company->id,
                    'first_message' => $incomingMessage,
                ],
            ]);
        }

        // Save incoming customer message
        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'message' => $incomingMessage,
        ]);

        // 3. Assemble Dynamic Knowledge Context
        $knowledgeContext = $this->buildKnowledgeContext($company);

        // 4. Generate AI Response
        $reply = $this->generateResponse($company, $incomingMessage, $knowledgeContext, $conversation);

        // Save outgoing bot reply
        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'bot',
            'message' => $reply,
            'metadata' => [
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
        ];
    }

    /**
     * Build knowledge base context dynamically from company profile and uploaded documents.
     */
    protected function buildKnowledgeContext(Company $company): string
    {
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
            $contextParts[] = "\n--- قوائم الأسعار والبيانات والخدمات المرفقة من ملفات المنشأة ---";
            foreach ($documents as $doc) {
                $contextParts[] = "### مستند ({$doc->title}) [نوع: {$doc->file_type}]:";
                $contextParts[] = $doc->extracted_text;
            }
        }

        return implode("\n", $contextParts);
    }

    /**
     * Generate AI answer using Gemini / OpenAI or dynamic data-driven fallback.
     */
    protected function generateResponse(Company $company, string $userMessage, string $knowledgeContext, Conversation $conversation): string
    {
        $geminiKey = env('GEMINI_API_KEY') ?: ($company->settings['gemini_api_key'] ?? null);
        $openaiKey = env('OPENAI_API_KEY') ?: ($company->settings['openai_api_key'] ?? null);

        // Universal Domain-Agnostic System Prompt
        $systemPrompt = $company->custom_system_prompt ?: "أنت المساعد الذكي والممثل الرسمي لخدمة عملاء منشأة ({$company->name}) عبر الواتساب.";
        $systemPrompt .= "\n\n[إرشادات وقواعد الرد المعتمد]:";
        $systemPrompt .= "\n1. استند في إجابتك حصراً ودقة إلى بيانات ومعلومات ومستندات المنشأة المرفقة أدناه.";
        $systemPrompt .= "\n2. افهم مقصد العميل بدقة مهما كانت لهجته المحلية أو أسلوبه أو أخطاؤه الإملائية أو صياغته المختصرة.";
        $systemPrompt .= "\n3. إذا سأل العميل عن أسعار أو خدمات أو أصناف أو مواصفات أو عروض، استخرج له أدق وأقرب إجابة متوفرة في البيانات المرفقة.";
        $systemPrompt .= "\n4. إذا سأل عن ميزانية أو صنف غير متوفر بذاته، وضّح له بلطف الخيارات والبدائل الأقرب المتاحة في البيانات، واقترح عليه الحلول المناسبة، أو وجهه للتواصل مع الإدارة.";
        $systemPrompt .= "\n5. ابدأ بترحيب مهني لبق باسم المنشأة ({$company->name})، واختم ببيانات التواصل وموقع المنشأة.";
        $systemPrompt .= "\n6. اجعل الرد منسقاً، مرتباً بنقاط واضحة، وسريع القراءة على شاشة محادثة الواتساب.";
        $systemPrompt .= "\n\n[بيانات ومستندات المنشأة المعتمدة / Company Knowledge Base]:\n" . $knowledgeContext;

        // 1. Try Google Gemini API
        if ($geminiKey) {
            $configuredModel = $company->settings['gemini_model'] ?? 'models/gemini-flash-lite-latest';
            if (!str_starts_with($configuredModel, 'models/')) {
                $configuredModel = 'models/' . $configuredModel;
            }

            $modelsToTry = array_values(array_unique([
                $configuredModel,
                'models/gemini-flash-lite-latest',
                'models/gemini-3.5-flash-lite',
                'models/gemini-3.5-flash',
            ]));

            foreach ($modelsToTry as $currentModel) {
                try {
                    $response = Http::timeout(5.0)
                        ->connectTimeout(2.5)
                        ->post("https://generativelanguage.googleapis.com/v1beta/{$currentModel}:generateContent?key={$geminiKey}", [
                            'contents' => [
                                [
                                    'role' => 'user',
                                    'parts' => [
                                        ['text' => $systemPrompt . "\n\nسؤال العميل:\n" . $userMessage]
                                    ]
                                ]
                            ],
                            'generationConfig' => [
                                'temperature' => 0.4,
                                'maxOutputTokens' => 800,
                            ]
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
                            return trim($aiText);
                        }
                    } else {
                        Log::warning("Gemini model {$currentModel} returned status " . $response->status());
                    }
                } catch (\Exception $e) {
                    Log::warning("Gemini timeout/error on {$currentModel}: " . $e->getMessage());
                }
            }
        }

        // 2. Try OpenAI API if configured (Fast failover)
        if ($openaiKey) {
            try {
                $response = Http::timeout(4.0)
                    ->withToken($openaiKey)
                    ->post("https://api.openai.com/v1/chat/completions", [
                        'model' => 'gpt-4o-mini',
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userMessage],
                        ],
                        'temperature' => 0.4,
                        'max_tokens' => 600,
                    ]);

                if ($response->successful()) {
                    $aiText = $response->json('choices.0.message.content');
                    if (!empty($aiText)) {
                        return trim($aiText);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("OpenAI API call failed: " . $e->getMessage());
            }
        }

        // 3. Universal Dynamic Database RAG Fallback Engine (100% domain-agnostic for any company)
        return $this->universalDatabaseRAG($company, $userMessage);
    }

    /**
     * Universal Dynamic Database RAG Fallback Engine.
     * Does NOT assume any specific industry. Bases all answers solely on the company's DB records & uploaded files.
     */
    protected function universalDatabaseRAG(Company $company, string $userMessage): string
    {
        $rawMsg = trim($userMessage);
        $msg = mb_strtolower($rawMsg);
        $normMsg = $this->normalizeArabic($msg);

        $isEnglish = preg_match('/[a-zA-Z]/', $msg) && !preg_match('/[\x{0600}-\x{06FF}]/u', $msg);
        $documents = $company->documents()->where('status', 'ready')->get();
        $contactNum = $company->phone ?: $company->whatsapp_number;
        $location = $company->address ?: 'المقر الرئيسي';

        // 1. Pure Greetings
        if (mb_strlen($rawMsg) < 25 && (
            preg_match('/^(مرحبا|السلام عليكم|سلام عليكم|هلا|صباح الخير|مساء الخير|اهلين|سلام|الو|ألو|مساء الورد|يا هلا)$/u', $normMsg) ||
            ($isEnglish && preg_match('/^(hi|hello|hey|good morning|good evening)$/i', $rawMsg))
        )) {
            $greeting = $company->settings['greeting_message'] ?? "أهلاً وسهلاً بك في {$company->name}! يسعدنا خدمتك.";
            return $greeting . " كيف يمكننا مساعدتك اليوم؟ تفضل بطلبك أو استفسارك وسأجيبك فوراً.";
        }

        // 2. Location / Address Inquiry
        if (preg_match('/(موقع|وين|عنوان|مكانكم|شارع|فرع|خريطه|خريطة|لوكيشن)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(location|where|address|place|map)\b/i', $rawMsg))) {
            $reply = "📍 موقعنا في {$company->name}:\n" . ($company->address ?: "تفضل بزيارتنا في مقرنا الرئيسي.");
            if ($company->google_maps_url) {
                $reply .= "\n🗺 رابط خرائط جوجل: " . $company->google_maps_url;
            }
            if ($contactNum) $reply .= "\n📞 هاتف التواصل: {$contactNum}";
            return $reply;
        }

        // 3. Working Hours Inquiry
        if (preg_match('/(دوام|وقت|ساعات|تفتحون|تغلقون|ايام|متى)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(hours|working hours|open|time|schedule)\b/i', $rawMsg))) {
            return "⏰ أوقات العمل في {$company->name}:\n" . ($company->working_hours ?: "من 8 صباحاً حتى 10 مساءً، ويسعدنا دائماً استقبالكم.") . "\n📞 هاتف: {$contactNum}";
        }

        // 4. Contact / Support Inquiry
        if (preg_match('/(رقم|اتصال|تواصل|هاتف|جوال|ايميل|دعم|خدمة عملاء|مسؤول)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(contact|phone|call|email|whatsapp|number|support)\b/i', $rawMsg))) {
            $reply = "📞 معلومات التواصل مع منشأة {$company->name}:\n";
            if ($company->phone) $reply .= "• هاتف: {$company->phone}\n";
            if ($company->whatsapp_number) $reply .= "• واتساب: {$company->whatsapp_number}\n";
            if ($company->support_phone) $reply .= "• مسؤول المبيعات المباشر: {$company->support_phone}\n";
            if ($company->address) $reply .= "📍 الموقع: {$company->address}";
            return trim($reply);
        }

        // 5. About Us / What do you offer
        if (preg_match('/(من انتم|من أنتم|نبذه|نبذة|عنكم|عن المنشاه|عن المنشأة|عن الشركه|عن الشركة|نشاطكم|مشروعكم|ماهو مشروعكم|ما هو مشروعكم)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(who are you|about|about us|company|services)\b/i', $rawMsg))) {
            $reply = "🏢 نبذة عن {$company->name}:\n";
            $reply .= ($company->about_us ?: "نحن نقدم أفضل المنتجات والخدمات ونهتم بتوفير أعلى معايير الجودة والأسعار لعملائنا.");
            if ($company->services_offered) {
                $reply .= "\n\n💡 أبرز ما نقدمه:\n" . $company->services_offered;
            }
            if ($contactNum) $reply .= "\n\n📞 للتواصل والاستفسار: {$contactNum}";
            return $reply;
        }

        // 6. Dynamic Budget / Numeric Inquiry (Evaluates customer's mentioned amount against any numeric price column in the DB)
        $userBudget = $this->extractBudgetFromMessage($normMsg);
        if ($userBudget !== null && $documents->count() > 0) {
            $budgetReply = $this->evaluateDynamicBudgetAgainstData($userBudget, $documents, $company);
            if ($budgetReply) {
                return $budgetReply;
            }
        }

        // 7. Universal Semantic Document Search (Scans any table/document row for query keywords)
        $matchedRows = $this->searchDynamicDocumentRows($documents, $normMsg);
        if (!empty($matchedRows)) {
            if (count($matchedRows) <= 3) {
                $replies = [];
                foreach ($matchedRows as $row) {
                    $replies[] = $this->formatDynamicRowDetails($row, $company);
                }
                $callToAction = "\n📞 للتواصل المباشر والطلب: {$contactNum}";
                if ($location) $callToAction .= "\n📍 الموقع: {$location}";
                return implode("\n\n───────────────\n\n", $replies) . $callToAction;
            }
            return $this->formatDynamicSummaryList($matchedRows, $company);
        }

        // 8. General Catalog / Offerings Request ("ايش عندكم", "اعرض لي", "قائمتكم", "اسعاركم", "الكتالوج")
        $isCatalogIntent = preg_match('/(قائمه|قائمة|كتالوج|منتجات|اصناف|أصناف|بضاعه|بضاعة|متوفر|موجود|ايش عندكم|شو عندكم|ماذا لديكم|ما لديكم|اعرض|ورني|عطني|اسعار|اسعاركم)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(list|catalog|catalogue|inventory|available|products|items|show me|prices)\b/i', $rawMsg));

        if ($isCatalogIntent && $documents->count() > 0) {
            $catalogResponse = $this->formatDynamicCatalogOverview($documents, $company);
            if ($catalogResponse) {
                return $catalogResponse;
            }
        }

        // 9. Universal Fallback: If no specific match, display company's offerings and top data preview
        if ($documents->count() > 0) {
            $catalogOverview = $this->formatDynamicCatalogOverview($documents, $company);
            if ($catalogOverview) {
                return "أهلاً بك في **{$company->name}**! يسعدنا خدمتك والرد على استفسارك.\n\n" . $catalogOverview;
            }
        }

        $defaultReply = "أهلاً بك في **{$company->name}**! يسعدنا تواصلك.\n";
        if ($company->about_us) $defaultReply .= "{$company->about_us}\n";
        if ($company->services_offered) $defaultReply .= "💡 خدماتنا: {$company->services_offered}\n";
        if ($contactNum) $defaultReply .= "📞 للتواصل المباشر: {$contactNum}\n";
        if ($company->address) $defaultReply .= "📍 الموقع: {$company->address}";

        return trim($defaultReply);
    }

    /**
     * Search any document's extracted rows for matching query tokens (domain-agnostic).
     */
    protected function searchDynamicDocumentRows($documents, string $normQuery): array
    {
        $matches = [];
        $words = preg_split('/\s+/u', $normQuery);

        // Filter out stop words that don't indicate a product or feature
        $stopWords = ['في', 'من', 'ما', 'هي', 'شو', 'ايش', 'وش', 'هل', 'عن', 'عندكم', 'موجود', 'متوفر', 'اريد', 'ابغى', 'ابي', 'احتاج', 'كم', 'سعر', 'اسعار', 'بكم', 'فقط', 'معي', 'عندي', 'ممكن', 'احصل', 'علا', 'على'];
        $filteredWords = array_values(array_diff($words, $stopWords));

        if (empty($filteredWords)) {
            return [];
        }

        foreach ($documents as $doc) {
            if (empty($doc->extracted_data) || !is_array($doc->extracted_data)) continue;

            foreach ($doc->extracted_data as $row) {
                if (!is_array($row) || empty(array_filter($row))) continue;

                $rowText = $this->normalizeArabic(implode(' ', array_values($row)));
                $score = 0;

                foreach ($filteredWords as $w) {
                    $w = trim($w);
                    if (mb_strlen($w) < 2) continue;

                    // Direct match
                    if (mb_strpos($rowText, $w) !== false) {
                        $score += 2;
                    }
                    // Strip leading "ال" or "الل" (Arabic definite article)
                    elseif (mb_strlen($w) > 3 && mb_substr($w, 0, 2) === 'ال') {
                        $sub = mb_substr($w, 2);
                        if (mb_strpos($rowText, $sub) !== false) {
                            $score += 2;
                        } elseif (mb_strlen($sub) > 3 && mb_substr($sub, 0, 2) === 'ال') {
                            $sub2 = mb_substr($sub, 2);
                            if (mb_strpos($rowText, $sub2) !== false) {
                                $score += 2;
                            }
                        }
                    }
                }

                if ($score > 0) {
                    $row['_score'] = $score;
                    $matches[] = $row;
                }
            }
        }

        // Sort by highest keyword score
        usort($matches, fn($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));

        return $matches;
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
        foreach ($row as $k => $v) {
            if (!empty(trim((string)$v)) && !is_numeric($v)) {
                $title = trim((string)$v);
                break;
            }
        }

        $lines[] = "✨ **" . ($title ?: 'تفاصيل الصنف / الخدمة') . "**";

        foreach ($row as $colName => $colValue) {
            $colName = trim((string)$colName);
            $colValue = trim((string)$colValue);
            if (empty($colValue)) continue;

            // Detect price columns
            if ($this->isPriceColumn($colName)) {
                $formatted = is_numeric(str_replace(',', '', $colValue)) ? number_format((float)str_replace(',', '', $colValue)) : $colValue;
                $lines[] = "💰 **{$colName}:** {$formatted} ريال";
            } else {
                $lines[] = "• **{$colName}:** {$colValue}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format a dynamic summary list of multiple matching rows.
     */
    protected function formatDynamicSummaryList(array $rows, Company $company): string
    {
        $lines = ["📋 **الخيارات والخدمات المطابقة لطلبك لدى {$company->name}:**\n"];
        $i = 1;

        foreach (array_slice($rows, 0, 7) as $row) {
            unset($row['_score'], $row['_num_price']);
            $firstCol = '';
            $priceCol = '';

            foreach ($row as $k => $v) {
                if (empty($firstCol) && !empty(trim((string)$v))) {
                    $firstCol = trim((string)$v);
                }
                if ($this->isPriceColumn($k) && !empty(trim((string)$v))) {
                    $formatted = is_numeric(str_replace(',', '', $v)) ? number_format((float)str_replace(',', '', $v)) : $v;
                    $priceCol = " — **{$formatted} ريال**";
                }
            }

            $lines[] = "{$i}. **{$firstCol}**{$priceCol}";
            $i++;
        }

        $contactNum = $company->phone ?: $company->whatsapp_number;
        $lines[] = "\n💡 اذكر اسم أي صنف أو خدمة لمعرفة تفاصيلها فوراً، أو تواصل معنا على: {$contactNum}";

        return implode("\n", $lines);
    }

    /**
     * Format dynamic catalog overview from whatever documents are stored for this company.
     */
    protected function formatDynamicCatalogOverview($documents, Company $company): ?string
    {
        $allRows = [];

        foreach ($documents as $doc) {
            if (!empty($doc->extracted_data) && is_array($doc->extracted_data)) {
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
                    return "📋 **قائمة البيانات المتاحة لدى {$company->name}:**\n\n" . mb_substr($doc->extracted_text, 0, 1200);
                }
            }
            return null;
        }

        $lines = ["📋 **قائمة الأصناف والخدمات المتاحة لدى {$company->name}:**\n"];
        $i = 1;

        foreach (array_slice($allRows, 0, 10) as $row) {
            $labelParts = [];
            $priceStr = '';

            foreach ($row as $k => $v) {
                $v = trim((string)$v);
                if (empty($v)) continue;

                if ($this->isPriceColumn($k)) {
                    $formatted = is_numeric(str_replace(',', '', $v)) ? number_format((float)str_replace(',', '', $v)) : $v;
                    $priceStr = " — **{$formatted} ريال**";
                } elseif (count($labelParts) < 2 && !is_numeric($v)) {
                    $labelParts[] = $v;
                }
            }

            $label = implode(' ', $labelParts);
            $lines[] = "{$i}. **{$label}**{$priceStr}";
            $i++;
        }

        $contactNum = $company->phone ?: $company->whatsapp_number;
        $lines[] = "\n✨ لمعرفة تفاصيل أي عنصر أو طلبه، يمكنك الرد باسمه أو الاتصال بنا: {$contactNum}";

        return implode("\n", $lines);
    }

    /**
     * Dynamically evaluate a customer's mentioned budget against document price columns.
     */
    protected function evaluateDynamicBudgetAgainstData(float $budget, $documents, Company $company): ?string
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

            return "أهلاً بك في **{$company->name}**! يسعدنا خدمتك والرد على استفسارك بدقة. 🤝\n\n" .
                "بخصوص ميزانيتك المذكورة (**{$formattedBudget} ريال**):\n" .
                "• أقل خيار متوفر لدينا يبدأ بسعر كاش (**{$cheapestPriceStr} ريال**) لـ ({$cheapestTitle}).\n\n" .
                "💡 **الخيارات المتاحة لخدمتك:**\n" .
                "• يمكنك الاستفادة من مبلغ (**{$formattedBudget} ريال**) كـ **دفعة أولى** في حال توفر نظام التقسيط أو التسهيلات الميسرة.\n" .
                "• كما نرحب بتواصلك المباشر مع الإدارة للاستفسار عن أي عروض أو خيارات خاصة: {$contactNum}\n" .
                "📍 الموقع: {$location}";
        }

        // Case 2: Budget fits items
        $matching = [];
        foreach ($rowsWithPrice as $row) {
            if ($row['_num_price'] <= $budget * 1.15) {
                $matching[] = $row;
            }
        }

        $lines = [
            "أهلاً بك في **{$company->name}**! يسعدنا تواصلك.",
            "بخصوص ميزانيتك المحددة (**{$formattedBudget} ريال**)، إليك أنسب الخيارات المتاحة لدينا في حدود هذه الميزانية:\n"
        ];

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

        $lines[] = "\n📞 للحجز والاستفسار المباشر: {$contactNum}\n📍 الموقع: {$location}";
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