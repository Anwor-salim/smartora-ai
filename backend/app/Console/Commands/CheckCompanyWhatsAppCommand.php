<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\WahaWhatsAppService;
use Illuminate\Console\Command;

class CheckCompanyWhatsAppCommand extends Command
{
    protected $signature = 'company:check-whatsapp {id?} {--sync}';
    protected $description = 'Check company WhatsApp connection status and WAHA session status';

    public function handle(WahaWhatsAppService $waha): int
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
        $this->line("حالة الاشتراك: {$company->status}");
        $this->line("حالة ربط الواتساب في النظام: {$company->whatsapp_session_status}");
        $this->line("تاريخ ووقت الربط: " . ($company->whatsapp_connected_at ? $company->whatsapp_connected_at->toDateTimeString() : 'لم يربط بعد'));

        // Check WAHA session via WahaWhatsAppService
        $this->info("\n=== فحص جلسات WAHA WhatsApp ===");
        $session = $waha->getSession();

        if ($session) {
            $name = $session['name'] ?? 'default';
            $status = $session['status'] ?? 'unknown';
            $phone = $waha->getConnectedPhone() ?? 'none';
            $pushName = $waha->getPushName() ?? 'none';

            $this->line("- الجلسة [{$name}]: الحالة = {$status}");
            $this->line("- الرقم المتصل = {$phone}");
            $this->line("- اسم الحساب = {$pushName}");

            if ($waha->isWorking()) {
                $this->info("✅ واتساب متصل وجاهز للرد الآلي الفوري!");
                if ($this->option('sync') || $company->whatsapp_session_status !== 'connected') {
                    $company->update([
                        'whatsapp_session_status' => 'connected',
                        'whatsapp_number' => $phone,
                        'whatsapp_connected_at' => $company->whatsapp_connected_at ?? now(),
                    ]);
                    $this->info("🔄 تم تحديث ومزامنة حالة المنشأة إلى 'متصل ونشط' بالرقم ({$phone}) بنجاح.");
                }
            } else {
                $this->warn("⚠️ الجلسة ليست في حالة WORKING (الحالة الحالية: {$status})");
            }
        } else {
            $this->error("تعذر جلب تفاصيل الجلسة من WAHA");
        }

        return 0;
    }
}
