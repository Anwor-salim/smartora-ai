<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AiChatService;
use Illuminate\Console\Command;

class TestCompanyChatCommand extends Command
{
    protected $signature = 'company:test-chat {message} {company_id?}';
    protected $description = 'Test AI WhatsApp response for a company';

    public function handle(): int
    {
        $companyId = $this->argument('company_id');
        $company = $companyId ? Company::find($companyId) : Company::latest()->first();

        if (!$company) {
            $this->error('Company not found.');
            return 1;
        }

        $msg = $this->argument('message');
        $this->info("الشركة: {$company->name} (ID: {$company->id})");
        $this->info("رقم الواتساب المسجل: {$company->whatsapp_number}");
        $this->info("السؤال التجريبي: {$msg}");

        $svc = app(AiChatService::class);
        $result = $svc->handleCustomerMessage($company, '967770001111', $msg);

        $this->info("\n=== الرد الآلي من البوت الذكي ===");
        $this->line($result['reply']);
        $this->info("\nالحالة: {$result['status']} | محادثة رقم: {$result['conversation_id']}");

        return 0;
    }
}
