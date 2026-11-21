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

        $systemPrompt = $company->custom_system_prompt ?: "أنت المساعد الذكي والممثل الرسمي لخدمة عملاء شركة ({$company->name}).";
        $systemPrompt .= "\n\nيجب عليك الإجابة بدقة بالغة واحترافية وبلهجة ودودة وواضحة مستنداً فقط إلى بيانات الشركة وقوائم الأسعار والملفات المرفقة أدناه.";
        $systemPrompt .= "\nإذا سأل العميل عن أسعار أو منتجات غير موجودة في البيانات، أخبره بلطف أن هذا الصنف غير متوفر حالياً ويمكنه التواصل مع الإدارة.";
        $systemPrompt .= "\n\n[بيانات الشركة والملفات المرفقة]:\n" . $knowledgeContext;

        // Try Gemini API if key is present
        if ($geminiKey) {
            try {
                $response = Http::timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$geminiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $systemPrompt . "\n\nسؤال العميل:\n" . $userMessage]
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
     * Smart local engine when external AI API key is not yet configured.
     */
    protected function smartContextualFallback(Company $company, string $userMessage, string $knowledgeContext): string
    {
        $msg = mb_strtolower($userMessage);

        // Greetings
        if (preg_match('/(مرحبا|السلام|هلا|صباح|مساء|اهلين|سلام)/u', $msg)) {
            $greeting = $company->settings['greeting_message'] ?? "أهلاً وسهلاً بك في {$company->name}! يسعدنا خدمتك.";
            return $greeting . " كيف يمكننا مساعدتك اليوم؟ تفضل بطلبك أو استفسارك وسأجيبك فوراً.";
        }

        // About us / Who are you
        if (preg_match('/(من انتم|من أنتم|نبذه|نبذة|عنكم|عن الشركه|عن الشركة|نشاطكم|مشروعكم|ماهو مشروعكم|ما هو مشروعكم)/u', $msg)) {
            $reply = "🏢 نبذة عن {$company->name}:\n";
            $reply .= ($company->about_us ?: "نحن نقدم خدمات تقنية متطورة وحلول الذكاء الاصطناعي للأعمال.");
            if ($company->services_offered) {
                $reply .= "\n\n💡 أبرز ما نقدمه:\n" . $company->services_offered;
            }
            return $reply;
        }

        // Services offered
        if (preg_match('/(خدمات|خدماتكم|ماذا تقدمون|ما تقدمون|ايش تقدمون|شو تقدمون|ميزات|مميزات)/u', $msg)) {
            if ($company->services_offered) {
                return "💡 الخدمات والحلول التي نقدمها في {$company->name}:\n\n" . $company->services_offered . "\n\nهل تود معرفة باقات الأسعار أو طريقة الاشتراك؟";
            }
        }

        // Packages and Pricing
        if (preg_match('/(باقه|باقة|باقات|اسعار|أسعار|سعر|تكلفه|تكلفة|اشتراك|اشتراكات|عرض|عروض|كم يكلف|كم السعر)/u', $msg)) {
            $docs = $company->documents()->where('status', 'ready')->get();
            foreach ($docs as $doc) {
                if (!empty($doc->extracted_data) && is_array($doc->extracted_data)) {
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
        if (preg_match('/(موقع|وين|عنوان|مكانكم|حضرموت|المكلا|شارع|فرع|خريطه|خريطة|لوكيشن)/u', $msg)) {
            $reply = "📍 موقعنا:\n" . ($company->address ?: "تفضل بزيارتنا في مقر الشركة.");
            if ($company->google_maps_url) {
                $reply .= "\n🗺 رابط الموقع على خرائط جوجل:\n" . $company->google_maps_url;
            }
            return $reply;
        }

        // Working hours
        if (preg_match('/(دوام|وقت|ساعات|تفتحون|تغلقون|ايام|متى)/u', $msg)) {
            return "⏰ أوقات العمل لدينا:\n" . ($company->working_hours ?: "على مدار 24 ساعة، ويسعدنا دائماً استقبال طلباتكم.");
        }

        // Contact / Phone
        if (preg_match('/(رقم|اتصال|تواصل|هاتف|جوال|ايميل|رد الي|رد آلي|دعم|خدمة عملاء)/u', $msg)) {
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
                                return "بناءً على قائمة بياناتنا المعتمدة، إليك التفاصيل:\n" . implode("\n", $details) . "\n\nهل تود تأكيد الطلب أو الاستفسار عن صنف آخر؟";
                            }
                        }
                    }
                }
            }
        }

        // Default friendly response
        return "شكراً لتواصلك مع {$company->name}!\nلقد استلمت استفسارك: \"{$userMessage}\".\nللحصول على تفاصيل دقيقة أو طلب الاشتراك، تفضل بالاستفسار عن باقاتنا أو اتصل بنا مباشرة على: " . ($company->phone ?: $company->whatsapp_number);
    }
}