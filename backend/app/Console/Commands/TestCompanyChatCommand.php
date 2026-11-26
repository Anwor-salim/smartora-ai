<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Conversation;
use App\Services\AiChatService;
use Illuminate\Console\Command;

class TestCompanyChatCommand extends Command
{
    protected $signature = 'company:test-chat {message} {company_id?} {--phone=967770001111} {--reset : Reset conversation history and context before sending}';
    protected $description = 'Test AI WhatsApp response for a company';

    public function handle(): int
    {
        $companyId = $this->argument('company_id');
        $company = $companyId ? Company::find($companyId) : Company::latest()->first();

        if (!$company) {
            $this->error('Company not found.');
            return 1;
        }

        $phone = (string) $this->option('phone');

        if ($this->option('reset')) {
            $existing = Conversation::where('company_id', $company->id)
                ->where('customer_phone', $phone)
                ->first();

            if ($existing) {
                $existing->messages()->delete();
                $existing->resetContext();
                $existing->update([
                    'last_message' => null,
                    'last_interaction_at' => null,
                ]);
                $this->warn("تم تصفير المحادثة السابقة للرقم {$phone}.");
            }
        }

        $msg = $this->argument('message');
        $this->info("الشركة: {$company->name} (ID: {$company->id})");
        $this->info("رقم الواتساب المسجل: {$company->whatsapp_number} | رقم العميل: {$phone}");
        $this->info("السؤال التجريبي: {$msg}");

        $svc = app(AiChatService::class);
        $result = $svc->handleCustomerMessage($company, $phone, $msg);

        $conversation = isset($result['conversation_id'])
            ? Conversation::find($result['conversation_id'])
            : null;
        $lastTopic = $conversation?->context_data['last_topic'] ?? '—';
        $engine = $result['engine'] ?? '—';
        $state = $result['conversation_state'] ?? ($conversation?->conversation_state ?? '—');

        $this->info("\n=== الرد الآلي من البوت الذكي ===");
        $this->line($result['reply'] ?? '[لا يوجد رد - البوت متوقف مؤقتاً]');
        $this->info("\nالحالة: {$result['status']} | محادثة رقم: {$result['conversation_id']} | المحرك: {$engine} | المرحلة: {$state} | آخر موضوع: {$lastTopic}");

        return 0;
    }
}
