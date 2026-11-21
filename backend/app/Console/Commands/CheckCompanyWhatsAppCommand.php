<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckCompanyWhatsAppCommand extends Command
{
    protected $signature = 'company:check-whatsapp {id?}';
    protected $description = 'Check company WhatsApp connection status and WAHA session status';

    public function handle(): int
    {
        $id = $this->argument('id');
        $company = $id ? Company::find($id) : Company::latest()->first();

        if (!$company) {
            $this->error('No company found.');
            return 1;
        }

        $this->info("=== تفاصيل المنشأة ===");
        $this->line("المعرف (ID): {$company->id}");
        $this->line("الاسم: {$company->name}");
        $this->line("المعرف اللطيف (Slug): {$company->slug}");
        $this->line("رقم الهاتف: {$company->phone}");
        $this->line("رقم الواتساب المسجل: {$company->whatsapp_number}");
        $this->line("رقم الرد الأولي / التحويل: {$company->support_phone}");
        $this->line("البريد الإلكتروني: {$company->email}");
        $this->line("حالة الاشتراك: {$company->status}");
        $this->line("حالة ربط الواتساب في النظام: {$company->whatsapp_session_status}");
        $this->line("تاريخ ووقت الربط: " . ($company->whatsapp_connected_at ? $company->whatsapp_connected_at->toDateTimeString() : 'لم يربط بعد'));

        // Check WAHA session
        $this->info("\n=== فحص جلسات WAHA WhatsApp ===");
        try {
            $response = Http::timeout(5)->get('http://smartora-waha:3000/api/sessions?all=true');
            if ($response->successful()) {
                $sessions = $response->json();
                $this->line("عدد جلسات WAHA النشطة: " . count($sessions));
                foreach ($sessions as $s) {
                    $name = $s['name'] ?? 'default';
                    $status = $s['status'] ?? 'unknown';
                    $me = $s['me']['id'] ?? ($s['me']['pushName'] ?? 'none');
                    $this->line("- الجلسة [{$name}]: الحالة = {$status} | الرقم المتصل = {$me}");
                }
            } else {
                $this->warn("WAHA HTTP Response: " . $response->status());
            }
        } catch (\Throwable $e) {
            $this->error("تعذر الاتصال بـ WAHA: " . $e->getMessage());
        }

        return 0;
    }
}
