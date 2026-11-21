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
            // Notify Super Admin and Company of new customer
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
        $geminiKey = env('GEMINI_API_KEY');
        $openaiKey = env('OPENAI_API_KEY');

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

        // Fallback: Smart contextual generator based on company data & keywords
        return $this->smartContextualFallback($company, $userMessage, $knowledgeContext);
    }

    /**
     * Smart local engine when external AI API key is not yet configured (Bilingual AR / EN).
     */
    protected function smartContextualFallback(Company $company, string $userMessage, string $knowledgeContext): string
    {
        $msg = mb_strtolower(trim($userMessage));
        // Detect English query: contains latin letters and no Arabic script
        $isEnglish = preg_match('/[a-zA-Z]/', $msg) && !preg_match('/[\x{0600}-\x{06FF}]/u', $msg);

        // Greetings
        if (preg_match('/(مرحبا|السلام|هلا|صباح|مساء|اهلين|سلام)/u', $msg) || ($isEnglish && preg_match('/\b(hi|hello|hey|greetings|good morning|good evening)\b/', $msg))) {
            if ($isEnglish) {
                return "Hello and welcome to {$company->name}! 👋\nWe are delighted to assist you. Feel free to ask about our services, pricing packages, location, or how to get started!";
            }
            $greeting = $company->settings['greeting_message'] ?? "أهلاً وسهلاً بك في {$company->name}! يسعدنا خدمتك.";
            return $greeting . " كيف يمكننا مساعدتك اليوم؟ تفضل بطلبك أو استفسارك وسأجيبك فوراً.";
        }

        // About us / Who are you
        if (preg_match('/(من انتم|من أنتم|نبذه|نبذة|عنكم|عن الشركه|عن الشركة|نشاطكم|مشروعكم|ماهو مشروعكم|ما هو مشروعكم)/u', $msg) ||
            ($isEnglish && preg_match('/\b(who are you|about|about us|company|what is smartora|tell me about)\b/', $msg))) {
            if ($isEnglish) {
                $reply = "🏢 About {$company->name}:\n";
                $reply .= ($company->about_us ?: "We provide smart AI customer service automation and intelligent business solutions.");
                if ($company->services_offered) {
                    $reply .= "\n\n💡 What we offer:\n" . $company->services_offered;
                }
                return $reply;
            }
            $reply = "🏢 نبذة عن {$company->name}:\n";
            $reply .= ($company->about_us ?: "نحن نقدم خدمات تقنية متطورة وحلول الذكاء الاصطناعي للأعمال.");
            if ($company->services_offered) {
                $reply .= "\n\n💡 أبرز ما نقدمه:\n" . $company->services_offered;
            }
            return $reply;
        }

        // Services offered
        if (preg_match('/(خدمات|خدماتكم|ماذا تقدمون|ما تقدمون|ايش تقدمون|شو تقدمون|ميزات|مميزات)/u', $msg) ||
            ($isEnglish && preg_match('/\b(service|services|features|what do you offer|what do you do)\b/', $msg))) {
            if ($isEnglish) {
                return "💡 Services and Solutions by {$company->name}:\n\n" . ($company->services_offered ?: "Automated AI customer support, 24/7 WhatsApp response, CRM integration, and instant setup.") . "\n\nWould you like to know our subscription packages and pricing?";
            }
            if ($company->services_offered) {
                return "💡 الخدمات والحلول التي نقدمها في {$company->name}:\n\n" . $company->services_offered . "\n\nهل تود معرفة باقات الأسعار أو طريقة الاشتراك؟";
            }
        }

        // Packages and Pricing
        if (preg_match('/(باقه|باقة|باقات|اسعار|أسعار|سعر|تكلفه|تكلفة|اشتراك|اشتراكات|عرض|عروض|كم يكلف|كم السعر)/u', $msg) ||
            ($isEnglish && preg_match('/\b(package|packages|price|prices|pricing|cost|subscription|plans|plan)\b/', $msg))) {
            $docs = $company->documents()->where('status', 'ready')->get();
            foreach ($docs as $doc) {
                if (!empty($doc->extracted_data) && is_array($doc->extracted_data)) {
                    if ($isEnglish) {
                        $packagesText = "📦 Available Packages & Pricing at {$company->name}:\n\n";
                        foreach ($doc->extracted_data as $pkg) {
                            $name = $pkg['اسم الباقة'] ?? $pkg['الباقة'] ?? $pkg['name'] ?? 'Package';
                            $duration = $pkg['المدة'] ?? $pkg['duration'] ?? '';
                            $price = $pkg['السعر'] ?? $pkg['price'] ?? '';
                            $features = $pkg['الميزات والخدمات المشمولة'] ?? $pkg['الميزات'] ?? $pkg['features'] ?? '';
                            
                            $packagesText .= "🔹 **{$name}**:\n";
                            if ($duration) $packagesText .= "   • Duration: {$duration}\n";
                            if ($price) $packagesText .= "   • Price: {$price}\n";
                            if ($features) $packagesText .= "   • Features: {$features}\n";
                            $packagesText .= "\n";
                        }
                        $packagesText .= "To subscribe and activate your service immediately, contact us at: " . ($company->phone ?: $company->whatsapp_number);
                        return trim($packagesText);
                    }

                    $packagesText = "📦 باقات وأسعار الاشتراك المتاحة لدى {$company->name}:\n\n";
                    foreach ($doc->extracted_data as $pkg) {
                        $name = $pkg['اسم الباقة'] ?? $pkg['الباقة'] ?? $pkg['name'] ?? 'باقة';
                        $duration = $pkg['المدة'] ?? $pkg['duration'] ?? '';
                        $price = $pkg['السعر'] ?? $pkg['price'] ?? '';
                        $features = $pkg['الميزات والخدمات المشمولة'] ?? $pkg['الميزات'] ?? $pkg['features'] ?? '';
                        
                        $packagesText .= "🔹 **{$name}**:\n";
                        if ($duration) $packagesText .= "   • المدة: {$duration}\n";
                        if ($price) $packagesText .= "   • السعر: {$price}\n";
                        if ($features) $packagesText .= "   • الميزات: {$features}\n";
                        $packagesText .= "\n";
                    }
                    $packagesText .= "للاشتراك وتفعيل الخدمة فوراً، يمكنك التواصل معنا مباشرة على الرقم: " . ($company->phone ?: $company->whatsapp_number);
                    return trim($packagesText);
                }
            }
        }

        // Location / Address
        if (preg_match('/(موقع|وين|عنوان|مكانكم|حضرموت|المكلا|شارع|فرع|خريطه|خريطة|لوكيشن)/u', $msg) ||
            ($isEnglish && preg_match('/\b(location|where|address|place|mukalla|hadhramout|map)\b/', $msg))) {
            if ($isEnglish) {
                $reply = "📍 Our Location:\n" . ($company->address ?: "Hadhramout - Al Mukalla");
                if ($company->google_maps_url) {
                    $reply .= "\n🗺 Google Maps Location:\n" . $company->google_maps_url;
                }
                return $reply;
            }
            $reply = "📍 موقعنا:\n" . ($company->address ?: "تفضل بزيارتنا في مقر الشركة.");
            if ($company->google_maps_url) {
                $reply .= "\n🗺 رابط الموقع على خرائط جوجل:\n" . $company->google_maps_url;
            }
            return $reply;
        }

        // Working hours
        if (preg_match('/(دوام|وقت|ساعات|تفتحون|تغلقون|ايام|متى)/u', $msg) ||
            ($isEnglish && preg_match('/\b(hours|working hours|open|time|schedule)\b/', $msg))) {
            if ($isEnglish) {
                return "⏰ Working Hours:\n" . ($company->working_hours ?: "24/7 round the clock to receive your requests.");
            }
            return "⏰ أوقات العمل لدينا:\n" . ($company->working_hours ?: "على مدار 24 ساعة، ويسعدنا دائماً استقبال طلباتكم.");
        }

        // Contact / Phone
        if (preg_match('/(رقم|اتصال|تواصل|هاتف|جوال|ايميل|رد الي|رد آلي|دعم|خدمة عملاء)/u', $msg) ||
            ($isEnglish && preg_match('/\b(contact|phone|call|email|whatsapp|number|support|helpdesk)\b/', $msg))) {
            if ($isEnglish) {
                $reply = "📞 Contact Information:\n";
                if ($company->phone) $reply .= "• Phone: {$company->phone}\n";
                if ($company->whatsapp_number) $reply .= "• Automated WhatsApp: {$company->whatsapp_number}\n";
                if ($company->support_phone) $reply .= "• Initial Support / Escalation: {$company->support_phone}\n";
                if ($company->email) $reply .= "• Email: {$company->email}\n";
                return trim($reply);
            }
            $reply = "📞 معلومات التواصل معنا:\n";
            if ($company->phone) $reply .= "• هاتف التواصل: {$company->phone}\n";
            if ($company->whatsapp_number) $reply .= "• رقم الرد الآلي عبر الواتساب: {$company->whatsapp_number}\n";
            if ($company->support_phone) $reply .= "• رقم مسؤول الرد الأولي / التحويل للدعم: {$company->support_phone}\n";
            if ($company->email) $reply .= "• البريد الإلكتروني: {$company->email}\n";
            return trim($reply);
        }

        // Check if query matches items in the uploaded documents
        $documents = $company->documents()->where('status', 'ready')->get();
        if ($documents->count() > 0) {
            foreach ($documents as $doc) {
                if (!empty($doc->extracted_data) && is_array($doc->extracted_data)) {
                    foreach ($doc->extracted_data as $item) {
                        $itemText = mb_strtolower(implode(' ', array_values($item)));
                        // Look for keyword overlap
                        $words = explode(' ', $msg);
                        foreach ($words as $w) {
                            if (mb_strlen($w) >= 3 && mb_strpos($itemText, $w) !== false) {
                                $details = [];
                                foreach ($item as $k => $v) {
                                    if (!empty($v)) $details[] = "• $k: $v";
                                }
                                if ($isEnglish) {
                                    return "Based on our registered records, here are the details:\n" . implode("\n", $details) . "\n\nWould you like to proceed or inquire about another item?";
                                }
                                return "بناءً على قائمة بياناتنا المعتمدة، إليك التفاصيل:\n" . implode("\n", $details) . "\n\nهل تود تأكيد الطلب أو الاستفسار عن صنف آخر؟";
                            }
                        }
                    }
                }
            }
        }

        // Default friendly response
        if ($isEnglish) {
            return "Thank you for contacting {$company->name}!\nWe have received your message: \"{$userMessage}\".\nFor immediate details or to subscribe, feel free to ask about our packages or contact us directly at: " . ($company->phone ?: $company->whatsapp_number);
        }
        return "شكراً لتواصلك مع {$company->name}!\nلقد استلمت استفسارك: \"{$userMessage}\".\nللحصول على تفاصيل دقيقة أو طلب الاشتراك، تفضل بالاستفسار عن باقاتنا أو اتصل بنا مباشرة على: " . ($company->phone ?: $company->whatsapp_number);
    }
}