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

        // 3. Assemble Knowledge Context
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
     * Build knowledge base context from company info and uploaded documents.
     */
    protected function buildKnowledgeContext(Company $company): string
    {
        $contextParts = [];

        $contextParts[] = "معلومات الشركة:";
        $contextParts[] = "- اسم الشركة: " . $company->name;
        if ($company->about_us) $contextParts[] = "- نبذة عن المنشأة ونشاطها: " . $company->about_us;
        if ($company->services_offered) $contextParts[] = "- الخدمات والمنتجات التي تقدمها: " . $company->services_offered;
        if ($company->phone) $contextParts[] = "- رقم التواصل: " . $company->phone;
        if ($company->whatsapp_number) $contextParts[] = "- رقم الواتساب المربوط: " . $company->whatsapp_number;
        if ($company->support_phone) $contextParts[] = "- رقم مسؤول الرد الأولي / التحويل للدعم البشري: " . $company->support_phone;
        if ($company->address) $contextParts[] = "- الموقع / العنوان: " . $company->address;
        if ($company->google_maps_url) $contextParts[] = "- رابط الموقع على الخريطة: " . $company->google_maps_url;
        if ($company->working_hours) $contextParts[] = "- أوقات وساعات الدوام: " . $company->working_hours;

        // Retrieve uploaded Excel / PDF documents
        $documents = $company->documents()->where('status', 'ready')->get();
        if ($documents->count() > 0) {
            $contextParts[] = "\n--- البيانات وقوائم الأسعار والخدمات المرفقة من ملفات الشركة ---";
            foreach ($documents as $doc) {
                $contextParts[] = "### مستند ({$doc->title}) [نوع: {$doc->file_type}]:";
                $contextParts[] = $doc->extracted_text;
            }
        }

        return implode("\n", $contextParts);
    }

    /**
     * Generate AI answer using Gemini / OpenAI or smart local semantic match.
     */
    protected function generateResponse(Company $company, string $userMessage, string $knowledgeContext, Conversation $conversation): string
    {
        $geminiKey = env('GEMINI_API_KEY') ?: ($company->settings['gemini_api_key'] ?? null);

        $systemPrompt = $company->custom_system_prompt ?: "أنت المساعد الذكي والممثل الرسمي لخدمة عملاء شركة ({$company->name}) عبر الواتساب.";
        $systemPrompt .= "\n\n[تعليمات هامة جداً للرد الذكي والسريع]:";
        $systemPrompt .= "\n1. افهم مقصد العميل فوراً مهما كانت لهجته المحلية (يمنية، خليجية، عامية) ومهما كانت صياغته مختصرة أو فيها أخطاء إملائية (مثل: موتر، جيب، صالون، كم يسوى، ابي اقساط، عطني سيارة بر، وش ارخص شي، بدل).";
        $systemPrompt .= "\n2. ابحث دائماً في بيانات ومستندات الشركة المرفقة عن أقرب جواب يخدم طلب العميل:";
        $systemPrompt .= "\n   - إذا سأل عن فئة معينة (بر، جيوب، عائلي، فخم، رخيص/اقتصادي، كهربائي، رياضي) أو ميزانية محددة، اعرض له السيارات الأقرب لطلبه فوراً مع أسعارها ومواصفاتها.";
        $systemPrompt .= "\n   - إذا سأل عن موديل غير موجود بذاته (مثل كامري)، أخبره بلطف باقتراب خيارات بديلة متوفرة في المعرض (مثل السوناتا أو اللكزس).";
        $systemPrompt .= "\n   - إذا سأل عن الأقساط أو البدل أو الفحص أو الضمان، وضّح له الإجابة بلباقة وأعطه أرقام التواصل وموقع المعرض.";
        $systemPrompt .= "\n3. ابدأ بترحيب لبق باسم المنشأة، واختم ببيانات التواصل وموقع المعرض، واجعل الرد مختصراً ومرتباً بنقاط واضحة للقراءة السريعة على شاشة الواتساب.";
        $systemPrompt .= "\n\n[بيانات الشركة والملفات المرفقة / Company Data & Knowledge]:\n" . $knowledgeContext;

        // Try Gemini 3.5 Flash with tight timeout for instant WhatsApp response
        if ($geminiKey) {
            try {
                $response = Http::timeout(4.5)
                    ->connectTimeout(2.5)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key={$geminiKey}", [
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
                            'thinkingConfig' => [
                                'thinkingBudget' => 0
                            ]
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
                    Log::warning("Gemini returned status " . $response->status() . " - switching to closest-match engine");
                }
            } catch (\Exception $e) {
                Log::warning("Gemini timeout/error: " . $e->getMessage() . " - switching to closest-match engine");
            }
        }

        // Ultra-Fast Closest-Match Semantic Engine
        return $this->smartContextualFallback($company, $userMessage, $knowledgeContext);
    }

    /**
     * Ultra-Fast Arabic Semantic & Closest-Match Engine (Bilingual AR / EN).
     */
    protected function smartContextualFallback(Company $company, string $userMessage, string $knowledgeContext): string
    {
        $rawMsg = trim($userMessage);
        $msg = mb_strtolower($rawMsg);
        $normMsg = $this->normalizeArabic($msg);

        $isEnglish = preg_match('/[a-zA-Z]/', $msg) && !preg_match('/[\x{0600}-\x{06FF}]/u', $msg);
        $documents = $company->documents()->where('status', 'ready')->get();
        $contactNum = $company->phone ?: $company->whatsapp_number;
        $location = $company->address ?: 'المعرض الرئيسي';

        // 1. Pure Greetings (only when short greeting without other questions)
        if (mb_strlen($rawMsg) < 25 && (
            preg_match('/^(مرحبا|السلام عليكم|سلام عليكم|هلا|صباح الخير|مساء الخير|اهلين|سلام|الو|ألو|مساء الورد|يا هلا)$/u', $normMsg) ||
            ($isEnglish && preg_match('/^(hi|hello|hey|good morning|good evening)$/i', $rawMsg))
        )) {
            if ($isEnglish) {
                return "Hello and welcome to {$company->name}! 👋\nWe are delighted to assist you. What car or service can we help you with today?";
            }
            $greeting = $company->settings['greeting_message'] ?? "أهلاً وسهلاً بك في {$company->name}! يسعدنا خدمتك.";
            return $greeting . " كيف يمكننا مساعدتك اليوم؟ تفضل بطلبك وسأجيبك فوراً عن السيارات المتاحة وأسعارها.";
        }

        // 2. Offroad / Desert / 4x4 / SUVs (جيوب / بر / صالون / تطعيس / دفع رباعي)
        if (preg_match('/(جيب|جيوب|بر|صحراء|تطعيس|اوف رود|أوف رود|دفع رباعي|فور ويل|4x4|صالون|مرتفع|مرتفعه|سيارات بر|موتر بر)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(offroad|4x4|suv|desert|4wd)\b/i', $rawMsg))) {
            $suvCars = $this->filterCarsByKeywords($documents, ['لاندكروزر', 'باترول', 'اكسبيديشن', 'كايين', 'x5', 'دفع رباعي', 'suv']);
            if (!empty($suvCars)) {
                $lines = ["🚗 **أفضل سيارات الدفع الرباعي والجيوب (للطرق الوعرة والبر) المتاحة لدى {$company->name}:**\n"];
                $i = 1;
                foreach ($suvCars as $car) {
                    $lines[] = $this->formatSingleCarRow($car, $i++);
                }
                $lines[] = "\n📍 المعرض: {$location} | 📞 للحجز والاستفسار: {$contactNum}";
                return implode("\n", $lines);
            }
        }

        // 3. Cheapest / Budget / Economical (أرخص سيارة / اقتصادية / ميزانية محدودة / بحدود 100-150 ألف)
        if (preg_match('/(ارخص|أرخص|رخيص|رخيصه|رخيصة|اقتصادي|اقتصاديه|اقتصادية|موفر|ميزانيه|ميزانية|أقل سعر|اقل سعر|اقل شي|أقل شي|صغيره|صغيرة|معقول)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(cheap|cheapest|economic|affordable|budget)\b/i', $rawMsg))) {
            $budgetSorted = $this->getAllCarsSortedByPrice($documents, 'asc');
            if (!empty($budgetSorted)) {
                $lines = ["💰 **أفضل السيارات الاقتصادية والأقل سعراً المتاحة لدى {$company->name}:**\n"];
                $i = 1;
                foreach (array_slice($budgetSorted, 0, 4) as $car) {
                    $lines[] = $this->formatSingleCarRow($car, $i++);
                }
                $lines[] = "\n💡 جميع السيارات جديدة ومضمونة ببطاقة جمركية.\n📞 للتواصل المباشر والحجز: {$contactNum}";
                return implode("\n", $lines);
            }
        }

        // 4. Luxury / VIP / Highest Tier (فخمة / فارهة / كشخة / VIP / أعلى فئة)
        if (preg_match('/(فخم|فخمه|فخمة|كشخه|كشخة|فاره|فارهه|فارهة|vip|اعلى فئه|أعلى فئة|اعلى نوع|أعلى نوع|مميزه|مميزة|ملكي)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(luxury|luxurious|vip|highest)\b/i', $rawMsg))) {
            $luxSorted = $this->getAllCarsSortedByPrice($documents, 'desc');
            if (!empty($luxSorted)) {
                $lines = ["👑 **أفخم السيارات الفارهة (VIP) المتاحة لدى {$company->name}:**\n"];
                $i = 1;
                foreach (array_slice($luxSorted, 0, 4) as $car) {
                    $lines[] = $this->formatSingleCarRow($car, $i++);
                }
                $lines[] = "\n✨ تشرفنا زيارتكم لمعاينة السيارات في المعرض: {$location}\n📞 هاتف: {$contactNum}";
                return implode("\n", $lines);
            }
        }

        // 5. Family Cars / Large / 7 Seats (عائلية / كبيرة / واسعة / 7 ركاب / سفر)
        if (preg_match('/(عائلي|عائليه|عائلية|كبيره|كبيرة|واسعه|واسعة|7 ركاب|سبع ركاب|أطفال|اطفال|سفر|عائله|عائلة)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(family|7 seats|spacious|large)\b/i', $rawMsg))) {
            $familyCars = $this->filterCarsByKeywords($documents, ['اكسبيديشن', 'باترول', 'لاندكروزر', 'عائلي']);
            if (!empty($familyCars)) {
                $lines = ["👨‍👩‍👧‍👦 **أفضل السيارات العائلية الكبيرة والواسعة (7-8 ركاب) لدى {$company->name}:**\n"];
                $i = 1;
                foreach ($familyCars as $car) {
                    $lines[] = $this->formatSingleCarRow($car, $i++);
                }
                $lines[] = "\n📞 لحجز موعد المعاينة أو تجربة القيادة: {$contactNum}";
                return implode("\n", $lines);
            }
        }

        // 6. Electric Cars (كهربائية / تسلا / هايبرد)
        if (preg_match('/(كهرباء|كهربائي|كهربائيه|كهربائية|شحن|هايبرد|تسلا|بطاريه|بطارية)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(electric|ev|tesla|hybrid)\b/i', $rawMsg))) {
            $electricCars = $this->filterCarsByKeywords($documents, ['تسلا', 'كهربائي', 'model y']);
            if (!empty($electricCars)) {
                $lines = ["⚡ **السيارات الكهربائية المتاحة لدى {$company->name}:**\n"];
                foreach ($electricCars as $car) {
                    $lines[] = $this->formatItemDetails($car, $isEnglish, $company);
                }
                $lines[] = "\n📞 للتواصل المباشر والحجز: {$contactNum}";
                return implode("\n\n", $lines);
            }
        }

        // 7. Installments & Financing (أقساط / تمويل / بنك / دفعة)
        if (preg_match('/(اقساط|أقساط|تقسيط|تمويل|بنك|دفعه|دفعة|شهر|شهري|شيك|شيكات)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(installment|installments|finance|financing|bank)\b/i', $rawMsg))) {
            return "💳 **نظام التمويل والأقساط لدى {$company->name}:**\n\nنعم، نوفر خيارات تمويلية ميسرة بالتعاون مع البنوك والجهات التمويلية المعتمدة.\n\n📋 **المتطلبات الأساسية عادة:**\n• تعريف بالراتب أو كشف حساب بنكي.\n• صورة الهوية / الإقامة سارية المفعول.\n• دفعة أولى حسب الفئة ونوع السيارة.\n\n📍 لمراجعة الحسبة الشهرية الدقيقة وتحديد السيارة، يشرفنا زيارتك لمعرضنا في: {$location}\n📞 أو الاتصال بمسؤول التمويل المباشر: {$contactNum}";
        }

        // 8. Trade-In / Exchange (بدل / استبدال / شراء سيارتي)
        if (preg_match('/(بدل|تبديل|استبدال|تقبلون بدل|تاخذون سيارتي|شراء سيارتي|تستبدلون)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(trade in|exchange|swap)\b/i', $rawMsg))) {
            return "🔄 **خدمة الاستبدال والبدل لدى {$company->name}:**\n\nنعم، نرحب بطلبك! يمكنك إحضار سيارتك الحالية لمعرضنا لتقييمها وفحصها من قبل خبرائنا، واستبدالها بإحدى سياراتنا الجديدة موديل 2025 مع دفع الفارق.\n\n📍 موقع المعرض للتقييم: {$location}\n📞 للتنسيق المسبق مع قسم التقييم: {$contactNum}";
        }

        // 9. Specific Item search in documents (e.g. Toyota, Mercedes, Lexus, BMW, Land Cruiser, etc.)
        $matchedItems = $this->searchDocumentItems($documents, $rawMsg);
        if (!empty($matchedItems)) {
            if (count($matchedItems) <= 3) {
                $replies = [];
                foreach ($matchedItems as $item) {
                    $replies[] = $this->formatItemDetails($item, $isEnglish, $company);
                }
                $callToAction = "\n📞 لحجز السيارة أو طلب المعاينة، يمكنك التواصل معنا مباشرة على الرقم: {$contactNum}\n📍 المعرض: {$location}";
                return implode("\n\n───────────────\n\n", $replies) . $callToAction;
            }
            return $this->formatItemListSummary($matchedItems, $isEnglish, $company);
        }

        // 10. Catalog Intent ("ما هي السيارات", "قائمة السيارات", "اعرض لي", "ايش عندكم", "الكتالوج")
        $isCatalogQuery = preg_match('/(سيارات|سياره|سيارة|قائمه|قائمة|كتالوج|منتجات|اصناف|أصناف|بضاعه|بضاعة|متوفر|موجود|ايش عندكم|شو عندكم|ماذا لديكم|ما لديكم|اعرض|ورني)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(cars|car|list|catalog|catalogue|inventory|available|models|products|items|show me)\b/i', $rawMsg));

        if ($isCatalogQuery && $documents->count() > 0) {
            $catalogResponse = $this->formatCatalogList($documents, $isEnglish, $company);
            if ($catalogResponse) {
                return $catalogResponse;
            }
        }

        // 11. Location / Address
        if (preg_match('/(موقع|وين|عنوان|مكانكم|حضرموت|المكلا|شارع|فرع|خريطه|خريطة|لوكيشن)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(location|where|address|place|mukalla|hadhramout|map)\b/i', $rawMsg))) {
            $reply = "📍 موقعنا في {$company->name}:\n" . ($company->address ?: "تفضل بزيارتنا في صالة العرض.");
            if ($company->google_maps_url) {
                $reply .= "\n🗺 رابط خرائط جوجل: " . $company->google_maps_url;
            }
            $reply .= "\n📞 هاتف التواصل: {$contactNum}";
            return $reply;
        }

        // 12. Working Hours
        if (preg_match('/(دوام|وقت|ساعات|تفتحون|تغلقون|ايام|متى)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(hours|working hours|open|time|schedule)\b/i', $rawMsg))) {
            return "⏰ أوقات العمل في {$company->name}:\n" . ($company->working_hours ?: "من 8 صباحاً حتى 10 مساءً، ويسعدنا دائماً استقبالكم.") . "\n📞 هاتف: {$contactNum}";
        }

        // 13. Contact / Phone
        if (preg_match('/(رقم|اتصال|تواصل|هاتف|جوال|ايميل|دعم|خدمة عملاء|مسؤول)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(contact|phone|call|email|whatsapp|number|support)\b/i', $rawMsg))) {
            $reply = "📞 معلومات التواصل مع شركة {$company->name}:\n";
            if ($company->phone) $reply .= "• هاتف: {$company->phone}\n";
            if ($company->whatsapp_number) $reply .= "• واتساب: {$company->whatsapp_number}\n";
            if ($company->support_phone) $reply .= "• مسؤول المبيعات: {$company->support_phone}\n";
            $reply .= "📍 المعرض: {$location}";
            return trim($reply);
        }

        // 14. Universal Closest-Match Fallback (أقرب جواب - لا نترك العميل بدون إفادة أبداً)
        if ($documents->count() > 0) {
            $catalog = $this->formatCatalogList($documents, $isEnglish, $company);
            if ($catalog) {
                return "أهلاً بك في شركة **{$company->name}**! يسعدنا خدمتك والرد على استفسارك.\n\nإليك قائمة بأحدث السيارات والأسعار المتاحة لدينا في المعرض حالياً:\n\n" . $catalog;
            }
        }

        return "أهلاً بك في شركة **{$company->name}**! يسعدنا خدمتك.\nيمكنك الاستفسار عن أي نوع سيارة أو طراز، أو طلب قائمة الأسعار والمواصفات فوراً.\n\n📍 المعرض: {$location}\n📞 هاتف: {$contactNum}";
    }

    /**
     * Helper to filter cars by multiple fuzzy keywords.
     */
    protected function filterCarsByKeywords($documents, array $keywords): array
    {
        $filtered = [];
        foreach ($documents as $doc) {
            if (empty($doc->extracted_data) || !is_array($doc->extracted_data)) continue;
            foreach ($doc->extracted_data as $car) {
                $brand = $car['الشركة المصنعة'] ?? $car['الشركة'] ?? '';
                $model = $car['الطراز'] ?? $car['الموديل'] ?? '';
                if (empty(trim($brand)) && empty(trim($model))) continue;

                $text = $this->normalizeArabic(implode(' ', array_values($car)));
                foreach ($keywords as $kw) {
                    $normKw = $this->normalizeArabic($kw);
                    if (mb_strpos($text, $normKw) !== false) {
                        $filtered[] = $car;
                        break;
                    }
                }
            }
        }
        return $filtered;
    }

    /**
     * Helper to sort all cars by price.
     */
    protected function getAllCarsSortedByPrice($documents, string $direction = 'asc'): array
    {
        $cars = [];
        foreach ($documents as $doc) {
            if (empty($doc->extracted_data) || !is_array($doc->extracted_data)) continue;
            foreach ($doc->extracted_data as $car) {
                $brand = $car['الشركة المصنعة'] ?? $car['الشركة'] ?? '';
                $model = $car['الطراز'] ?? $car['الموديل'] ?? '';
                if (empty(trim($brand)) && empty(trim($model))) continue;

                $rawPrice = $car['السعر (ريال سعودي)'] ?? $car['السعر'] ?? '0';
                $numPrice = (float)preg_replace('/[^0-9]/', '', (string)$rawPrice);
                if ($numPrice <= 0) continue; // Skip zero/empty price rows
                $car['_num_price'] = $numPrice;
                $cars[] = $car;
            }
        }

        usort($cars, function ($a, $b) use ($direction) {
            return $direction === 'asc' 
                ? ($a['_num_price'] <=> $b['_num_price']) 
                : ($b['_num_price'] <=> $a['_num_price']);
        });

        return $cars;
    }

    /**
     * Format a clean single line for a car row.
     */
    protected function formatSingleCarRow(array $car, int $index): string
    {
        $brand = $car['الشركة المصنعة'] ?? $car['الشركة'] ?? '';
        $model = $car['الطراز'] ?? $car['الموديل'] ?? '';
        $year = $car['سنة الصنع'] ?? '2025';
        $category = $car['الفئة'] ?? '';
        $price = $car['السعر (ريال سعودي)'] ?? $car['السعر'] ?? '';
        $engine = $car['نوع المحرك'] ?? '';

        $priceStr = '';
        if ($price) {
            $formattedPrice = is_numeric(str_replace(',', '', $price)) ? number_format((float)str_replace(',', '', $price)) : $price;
            $priceStr = " — **{$formattedPrice} ر.س**";
        }

        $extra = trim("{$category} " . ($engine ? "({$engine})" : ''));
        return "{$index}. **{$brand} {$model} {$year}**" . ($extra ? " ({$extra})" : "") . $priceStr;
    }

    /**
     * Search extracted document items for keyword match with smart normalization.
     */
    protected function searchDocumentItems($documents, string $query): array
    {
        $matches = [];
        $cleanQuery = $this->normalizeArabic($query);

        // Words from query, stripping common Arabic particles
        $words = preg_split('/\s+/u', $cleanQuery);
        $searchTerms = [];
        foreach ($words as $w) {
            $w = trim($w);
            // Strip leading 'ال'
            if (mb_strlen($w) > 3 && (mb_substr($w, 0, 2) === 'ال')) {
                $sub = mb_substr($w, 2);
                if (mb_strlen($sub) > 3 && (mb_substr($sub, 0, 2) === 'ال')) {
                    $searchTerms[] = mb_substr($sub, 2);
                }
                $searchTerms[] = $sub;
            }
            if (mb_strlen($w) >= 2) {
                $searchTerms[] = $w;
            }
        }
        $searchTerms = array_unique(array_filter($searchTerms));

        // Skip generic stop words
        $stopWords = ['سياره', 'سيارة', 'سيارات', 'نريد', 'اريد', 'ابغى', 'عندكم', 'موجود', 'متوفر', 'كم', 'سعر', 'اسعار', 'في', 'من', 'ما', 'هي', 'شو', 'ايش', 'وش'];
        $filteredTerms = array_values(array_diff($searchTerms, $stopWords));

        // Expand with common Arabic dialect synonyms
        $synonymsMap = [
            'مرسدس' => ['مرسيدس', 's500'],
            'بنز' => ['مرسيدس'],
            'يخت' => ['مرسيدس', 's500'],
            'كروزر' => ['لاندكروزر', 'تويوتا'],
            'لاند' => ['لاندكروزر'],
            'صالون' => ['لاندكروزر', 'باترول'],
            'هونداي' => ['هيونداي', 'سوناتا'],
            'بي ام' => ['بي إم دبليو', 'x5'],
            'اكسبدشن' => ['إكسبيديشن', 'اكسبيديشن', 'فورد'],
            'باترول' => ['نيسان', 'بلاتينيوم'],
            'كايين' => ['بورش'],
            'كي8' => ['كيا', 'k8'],
            'ارنبه' => ['لكزس', 'es350'],
        ];

        foreach ($filteredTerms as $ft) {
            foreach ($synonymsMap as $keySyn => $equivalents) {
                if (mb_strpos($ft, $keySyn) !== false) {
                    $filteredTerms = array_merge($filteredTerms, $equivalents);
                }
            }
        }
        $filteredTerms = array_unique($filteredTerms);

        if (empty($filteredTerms)) {
            return [];
        }

        foreach ($documents as $doc) {
            if (empty($doc->extracted_data) || !is_array($doc->extracted_data)) {
                continue;
            }

            foreach ($doc->extracted_data as $item) {
                $itemText = mb_strtolower(implode(' ', array_values($item)));
                $normItemText = $this->normalizeArabic($itemText);

                $matched = false;
                foreach ($filteredTerms as $term) {
                    if (mb_strpos($normItemText, $term) !== false) {
                        $matched = true;
                        break;
                    }
                }

                if ($matched) {
                    $matches[] = $item;
                }
            }
        }

        return $matches;
    }

    /**
     * Format full details of a single product/car.
     */
    protected function formatItemDetails(array $item, bool $isEnglish, Company $company): string
    {
        $brand = $item['الشركة المصنعة'] ?? $item['الشركة'] ?? $item['brand'] ?? $item['make'] ?? '';
        $model = $item['الطراز'] ?? $item['النوع'] ?? $item['الموديل'] ?? $item['model'] ?? '';
        $year = $item['سنة الصنع'] ?? $item['الموديل'] ?? $item['year'] ?? '';
        $category = $item['الفئة'] ?? $item['category'] ?? '';
        $price = $item['السعر (ريال سعودي)'] ?? $item['السعر'] ?? $item['price'] ?? '';
        $colorExt = $item['اللون الخارجي'] ?? $item['color_ext'] ?? '';
        $colorInt = $item['اللون الداخلي'] ?? $item['color_int'] ?? '';
        $engine = $item['نوع المحرك'] ?? $item['المحرك'] ?? $item['engine'] ?? '';
        $horsepower = $item['قوة المحرك (حصان)'] ?? $item['القوة'] ?? $item['hp'] ?? '';
        $gearbox = $item['ناقل الحركة'] ?? $item['القير'] ?? $item['transmission'] ?? '';
        $driveType = $item['نوع الدفع'] ?? $item['دفع'] ?? $item['drivetrain'] ?? '';
        $condition = $item['الحالة'] ?? $item['condition'] ?? 'جديدة';
        $features = $item['مواصفات إضافية'] ?? $item['المواصفات'] ?? $item['features'] ?? '';

        $title = trim("{$brand} {$model} {$year}");
        if (empty($title)) {
            $title = $item['اسم الباقة'] ?? $item['الاسم'] ?? $item['name'] ?? 'تفاصيل العنصر';
        }

        $lines = [];
        $lines[] = "🚗 **{$title}** (" . ($category ?: 'المواصفات') . ")";
        if ($price) {
            $formattedPrice = is_numeric(str_replace(',', '', $price)) ? number_format((float)str_replace(',', '', $price)) : $price;
            $lines[] = "💰 **السعر:** {$formattedPrice} ريال سعودي";
        }
        if ($condition) {
            $lines[] = "✨ **الحالة:** {$condition}";
        }
        if ($engine || $horsepower) {
            $engineStr = $engine;
            if ($horsepower) $engineStr .= " ({$horsepower} حصان)";
            $lines[] = "⚙️ **المحرك:** {$engineStr}";
        }
        if ($gearbox || $driveType) {
            $gearStr = trim("{$gearbox} - {$driveType}", " -");
            $lines[] = "🕹 **ناقل الحركة والدفع:** {$gearStr}";
        }
        if ($colorExt || $colorInt) {
            $colorStr = "";
            if ($colorExt) $colorStr .= "خارجي: {$colorExt}";
            if ($colorInt) $colorStr .= " | داخلي: {$colorInt}";
            $lines[] = "🎨 **الألوان:** {$colorStr}";
        }
        if ($features) {
            $lines[] = "🌟 **أبرز المواصفات:** {$features}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format a summary list of multiple items.
     */
    protected function formatItemListSummary(array $items, bool $isEnglish, Company $company): string
    {
        $lines = [];
        $lines[] = "🚗 **السيارات المطابقة لطلبك لدى " . $company->name . ":**\n";

        $i = 1;
        foreach (array_slice($items, 0, 8) as $item) {
            $brand = $item['الشركة المصنعة'] ?? $item['الشركة'] ?? '';
            $model = $item['الطراز'] ?? $item['الموديل'] ?? '';
            $year = $item['سنة الصنع'] ?? '';
            $price = $item['السعر (ريال سعودي)'] ?? $item['السعر'] ?? '';
            $category = $item['الفئة'] ?? '';

            $priceStr = '';
            if ($price) {
                $formattedPrice = is_numeric(str_replace(',', '', $price)) ? number_format((float)str_replace(',', '', $price)) : $price;
                $priceStr = " — {$formattedPrice} ر.س";
            }

            $lines[] = "{$i}. **{$brand} {$model} {$year}** ({$category}){$priceStr}";
            $i++;
        }

        $contactNum = $company->phone ?: $company->whatsapp_number;
        $lines[] = "\n💡 لمعرفة تفاصيل ومواصفات أي سيارة، فقط اذكر اسمها وسأعطيك كامل تفاصيلها فوراً!";
        $lines[] = "📞 للحجز والاستفسار المباشر: {$contactNum}";

        return implode("\n", $lines);
    }

    /**
     * Format full catalog list from uploaded documents.
     */
    protected function formatCatalogList($documents, bool $isEnglish, Company $company): ?string
    {
        $allCars = [];

        foreach ($documents as $doc) {
            if (!empty($doc->extracted_data) && is_array($doc->extracted_data)) {
                foreach ($doc->extracted_data as $row) {
                    $brand = $row['الشركة المصنعة'] ?? $row['الشركة'] ?? '';
                    $model = $row['الطراز'] ?? $row['الموديل'] ?? '';
                    if (!empty($brand) || !empty($model)) {
                        $allCars[] = $row;
                    }
                }
            }
        }

        if (empty($allCars)) {
            // Check if document has markdown table in extracted_text
            foreach ($documents as $doc) {
                if (!empty($doc->extracted_text) && str_contains($doc->extracted_text, '|')) {
                    return "📋 **قائمة الأسعار والخدمات المتاحة لدى {$company->name}:**\n\n" . mb_substr($doc->extracted_text, 0, 1500);
                }
            }
            return null;
        }

        $lines = [];
        $lines[] = "🚗 **قائمة السيارات المتاحة وأسعارها لدى " . $company->name . ":**\n";

        $i = 1;
        foreach ($allCars as $car) {
            $brand = $car['الشركة المصنعة'] ?? '';
            $model = $car['الطراز'] ?? '';
            $year = $car['سنة الصنع'] ?? '2025';
            $category = $car['الفئة'] ?? '';
            $price = $car['السعر (ريال سعودي)'] ?? $car['السعر'] ?? '';

            $priceStr = '';
            if ($price) {
                $formattedPrice = is_numeric(str_replace(',', '', $price)) ? number_format((float)str_replace(',', '', $price)) : $price;
                $priceStr = " — **{$formattedPrice} ر.س**";
            }

            $catStr = $category ? " ({$category})" : "";
            $lines[] = "{$i}. **{$brand} {$model} {$year}**{$catStr}{$priceStr}";
            $i++;
        }

        $contactNum = $company->phone ?: $company->whatsapp_number;
        $lines[] = "\n✨ **هل ترغب في معرفة كامل المواصفات أو حجز أي سيارة؟**";
        $lines[] = "فقط اكتب اسم السيارة وسأعرض لك صورها ومواصفاتها، أو تواصل معنا مباشرة على: {$contactNum}";

        return implode("\n", $lines);
    }

    /**
     * Arabic text normalizer (unifies alef forms, taa marboota, removes diacritics).
     */
    protected function normalizeArabic(string $text): string
    {
        $text = mb_strtolower($text);
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