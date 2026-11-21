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
        $openaiKey = env('OPENAI_API_KEY') ?: ($company->settings['openai_api_key'] ?? null);

        $systemPrompt = $company->custom_system_prompt ?: "أنت المساعد الذكي والممثل الرسمي لخدمة عملاء شركة ({$company->name}) / You are the official AI customer service assistant for ({$company->name}).";
        $systemPrompt .= "\n\nيجب عليك الإجابة بدقة بالغة واحترافية وبنفس لغة العميل (العربية إذا تحدث بالعربية، والإنجليزية إذا سأل بالإنجليزية).";
        $systemPrompt .= "\nYou must reply accurately and politely in the exact language used by the customer (English if they speak English, Arabic if they speak Arabic), based strictly on the company information and documents provided.";
        $systemPrompt .= "\nإذا سأل العميل عن أسعار أو منتجات غير موجودة في البيانات، أخبره بلطف أن هذا الصنف غير متوفر حالياً ويمكنه التواصل مع الإدارة. / If the customer asks for unavailable items, politely inform them and direct them to management.";
        $systemPrompt .= "\n\n[بيانات الشركة والملفات المرفقة / Company Data & Knowledge]:\n" . $knowledgeContext;

        // Try Gemini API if key is present
        if ($geminiKey) {
            try {
                $response = Http::timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$geminiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $systemPrompt . "\n\nCustomer Inquiry / سؤال العميل:\n" . $userMessage]
                            ]
                        ]
                    ]
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $aiText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($aiText) {
                        return trim($aiText);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Gemini API call failed: " . $e->getMessage());
            }
        }

        // High-Intelligence Contextual & Document Fallback Engine
        return $this->smartContextualFallback($company, $userMessage, $knowledgeContext);
    }

    /**
     * Smart local engine when external AI API key is not yet configured (Bilingual AR / EN).
     */
    protected function smartContextualFallback(Company $company, string $userMessage, string $knowledgeContext): string
    {
        $rawMsg = trim($userMessage);
        $msg = mb_strtolower($rawMsg);
        $normMsg = $this->normalizeArabic($msg);

        // Detect English query: contains latin letters and no Arabic script
        $isEnglish = preg_match('/[a-zA-Z]/', $msg) && !preg_match('/[\x{0600}-\x{06FF}]/u', $msg);

        // 1. Pure Greetings (only when short greeting without additional question)
        if (mb_strlen($rawMsg) < 25 && (
            preg_match('/^(مرحبا|السلام عليكم|سلام عليكم|هلا|صباح الخير|مساء الخير|اهلين|سلام|الو|ألو)$/u', $normMsg) ||
            ($isEnglish && preg_match('/^(hi|hello|hey|good morning|good evening)$/i', $rawMsg))
        )) {
            if ($isEnglish) {
                return "Hello and welcome to {$company->name}! 👋\nWe are delighted to assist you. Feel free to ask about our available products, pricing, location, or services!";
            }
            $greeting = $company->settings['greeting_message'] ?? "أهلاً وسهلاً بك في {$company->name}! يسعدنا خدمتك.";
            return $greeting . " كيف يمكننا مساعدتك اليوم؟ تفضل بطلبك أو استفسارك وسأجيبك فوراً.";
        }

        // 2. Fetch all ready documents
        $documents = $company->documents()->where('status', 'ready')->get();

        // 3. Check for Catalog / List Intent ("ما هي السيارات", "قائمة السيارات", "اعرض لي", "ايش عندكم", "الكتالوج")
        $isCatalogQuery = preg_match('/(سيارات|سياره|سيارة|قائمه|قائمة|كتالوج|منتجات|اصناف|أصناف|بضاعه|بضاعة|متوفر|موجود|ايش عندكم|شو عندكم|ماذا لديكم|ما لديكم|اعرض|ورني)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(cars|car|list|catalog|catalogue|inventory|available|models|products|items|show me)\b/i', $rawMsg));

        // 4. Check for Specific Item search in documents (e.g. Toyota, Mercedes, Lexus, BMW, Land Cruiser, etc.)
        $matchedItems = $this->searchDocumentItems($documents, $rawMsg);

        // If specific items matched with high confidence:
        if (!empty($matchedItems)) {
            // If only 1 or 2 items matched, give full details
            if (count($matchedItems) <= 3) {
                $replies = [];
                foreach ($matchedItems as $item) {
                    $replies[] = $this->formatItemDetails($item, $isEnglish, $company);
                }
                $contactNum = $company->phone ?: $company->whatsapp_number;
                $callToAction = $isEnglish 
                    ? "\n📞 For booking or inquiries, contact us directly at: {$contactNum}"
                    : "\n📞 لحجز السيارة أو طلب المعاينة، يمكنك التواصل معنا مباشرة على الرقم: {$contactNum}";

                return implode("\n\n───────────────\n\n", $replies) . $callToAction;
            }

            // If multiple items matched (e.g. query "SUV" or "2025" or multiple brands), format summary list
            return $this->formatItemListSummary($matchedItems, $isEnglish, $company);
        }

        // If user is asking for catalog or what is available
        if ($isCatalogQuery && $documents->count() > 0) {
            $catalogResponse = $this->formatCatalogList($documents, $isEnglish, $company);
            if ($catalogResponse) {
                return $catalogResponse;
            }
        }

        // 5. Purchase Intent ("نريد سيارة", "ابغى اشتري سيارة", "اريد سياره")
        if (preg_match('/(اريد|ابغى|ابغي|نشتري|اشتري|شراء|طلب|احتاج|نبي|نريد)\s+(سياره|سيارة|سيارات|موتور|مركبه|مركبة)/u', $normMsg) ||
            preg_match('/^(نريد سياره|نريد سيارة|ابغى سيارة|ابغى سياره|اريد سيارة)$/u', $normMsg)) {
            $catalogResponse = $this->formatCatalogList($documents, $isEnglish, $company);
            if ($catalogResponse) {
                $intro = "أهلاً بك! يسعدنا جداً خدمتك. لدينا أحدث تشكيلة من السيارات المعتمدة والمضمونة:\n\n";
                return $intro . $catalogResponse;
            }
        }

        // 6. About Us / Who Are You
        if (preg_match('/(من انتم|من أنتم|نبذه|نبذة|عنكم|عن الشركه|عن الشركة|نشاطكم|مشروعكم|ماهو مشروعكم|ما هو مشروعكم)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(who are you|about|about us|company|what is smartora|tell me about)\b/i', $rawMsg))) {
            if ($isEnglish) {
                $reply = "🏢 About {$company->name}:\n";
                $reply .= ($company->about_us ?: "We provide high quality products, services, and smart customer care.");
                if ($company->services_offered) {
                    $reply .= "\n\n💡 What we offer:\n" . $company->services_offered;
                }
                return $reply;
            }
            $reply = "🏢 نبذة عن {$company->name}:\n";
            $reply .= ($company->about_us ?: "نحن نقدم أفضل المنتجات والخدمات ونهتم بتوفير أفضل الأسعار لعملائنا.");
            if ($company->services_offered) {
                $reply .= "\n\n💡 أبرز ما نقدمه:\n" . $company->services_offered;
            }
            return $reply;
        }

        // 7. Services Offered
        if (preg_match('/(خدمات|خدماتكم|ماذا تقدمون|ما تقدمون|ايش تقدمون|شو تقدمون|ميزات|مميزات)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(service|services|features|what do you offer|what do you do)\b/i', $rawMsg))) {
            if ($company->services_offered) {
                return "💡 الخدمات والحلول التي نقدمها في {$company->name}:\n\n" . $company->services_offered . "\n\nهل تود استعراض قائمة السيارات والأسعار المتاحة؟";
            }
        }

        // 8. Location / Address
        if (preg_match('/(موقع|وين|عنوان|مكانكم|حضرموت|المكلا|شارع|فرع|خريطه|خريطة|لوكيشن)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(location|where|address|place|mukalla|hadhramout|map)\b/i', $rawMsg))) {
            if ($isEnglish) {
                $reply = "📍 Our Location:\n" . ($company->address ?: "Please visit us at our showroom.");
                if ($company->google_maps_url) {
                    $reply .= "\n🗺 Google Maps Location:\n" . $company->google_maps_url;
                }
                return $reply;
            }
            $reply = "📍 موقعنا:\n" . ($company->address ?: "تفضل بزيارتنا في صالة العرض.");
            if ($company->google_maps_url) {
                $reply .= "\n🗺 رابط الموقع على خرائط جوجل:\n" . $company->google_maps_url;
            }
            return $reply;
        }

        // 9. Working Hours
        if (preg_match('/(دوام|وقت|ساعات|تفتحون|تغلقون|ايام|متى)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(hours|working hours|open|time|schedule)\b/i', $rawMsg))) {
            if ($isEnglish) {
                return "⏰ Working Hours:\n" . ($company->working_hours ?: "24/7 round the clock to receive your requests.");
            }
            return "⏰ أوقات العمل لدينا:\n" . ($company->working_hours ?: "على مدار 24 ساعة، ويسعدنا دائماً استقبال طلباتكم وزيارتكم.");
        }

        // 10. Contact / Phone
        if (preg_match('/(رقم|اتصال|تواصل|هاتف|جوال|ايميل|دعم|خدمة عملاء|مسؤول)/u', $normMsg) ||
            ($isEnglish && preg_match('/\b(contact|phone|call|email|whatsapp|number|support|helpdesk)\b/i', $rawMsg))) {
            $reply = "📞 معلومات التواصل معنا:\n";
            if ($company->phone) $reply .= "• هاتف التواصل: {$company->phone}\n";
            if ($company->whatsapp_number) $reply .= "• رقم الواتساب المربوط: {$company->whatsapp_number}\n";
            if ($company->support_phone) $reply .= "• مسؤول المبيعات المباشر: {$company->support_phone}\n";
            if ($company->email) $reply .= "• البريد الإلكتروني: {$company->email}\n";
            return trim($reply);
        }

        // 11. If there's an uploaded document, provide an active preview list
        if ($documents->count() > 0) {
            $catalog = $this->formatCatalogList($documents, $isEnglish, $company);
            if ($catalog) {
                return "أهلاً بك في {$company->name}! تفضل بالاطلاع على قائمة السيارات والأسعار المتاحة لدينا:\n\n" . $catalog;
            }
        }

        // Default friendly response
        if ($isEnglish) {
            return "Thank you for contacting {$company->name}!\nWe have received your message: \"{$userMessage}\".\nFeel free to ask about our available cars, pricing, or contact us directly at: " . ($company->phone ?: $company->whatsapp_number);
        }
        return "شكراً لتواصلك مع {$company->name}!\nنحن بخدمتك دائماً. يمكنك الاستفسار عن أي نوع سيارة أو طراز، أو طلب قائمة الأسعار والمواصفات فوراً.\nللتواصل المباشر: " . ($company->phone ?: $company->whatsapp_number);
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

        // Skip generic words that don't indicate a specific brand/model
        $stopWords = ['سياره', 'سيارة', 'سيارات', 'نريد', 'اريد', 'ابغى', 'عندكم', 'موجود', 'متوفر', 'كم', 'سعر', 'اسعار', 'في', 'من', 'ما', 'هي', 'شو', 'ايش'];
        $filteredTerms = array_diff($searchTerms, $stopWords);

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