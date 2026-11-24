<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\WahaWhatsAppService;
use Illuminate\Console\Command;

class CheckCompanyWhatsAppCommand extends Command
{
    protected $signature = 'company:check-whatsapp {id?} {--sync} {--all : Check all companies and their WAHA sessions}';
    protected $description = 'Check company WhatsApp connection status and WAHA session status';

    public function handle(WahaWhatsAppService $waha): int
    {
        if ($this->option('all')) {
            $companies = Company::orderBy('id')->get();
            if ($companies->isEmpty()) {
                $this->error('No companies found.');
                return 1;
            }

            foreach ($companies as $company) {
                $this->inspectCompany($company, $waha);
                $this->line(str_repeat('─', 60));
            }

            return 0;
        }

        $id = $this->argument('id');
        $company = $id ? Company::find($id) : Company::latest()->first();

        if (!$company) {
            $this->error('No company found.');
            return 1;
        }

        $this->inspectCompany($company, $waha);
        return 0;
    }

    protected function inspectCompany(Company $company, WahaWhatsAppService $waha): void
    {

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

        $this->info("\n=== ملفات المعرفة والمستندات (Documents) ===");
        $docs = $company->documents()->get();
        $this->line("عدد الملفات المرتبطة: " . $docs->count());
        foreach ($docs as $d) {
            $this->line("- [#{$d->id}] {$d->title} ({$d->file_type}) | الحالة: {$d->status} | الأصناف: {$d->total_items} | طول النص: " . mb_strlen($d->extracted_text ?? ''));
            if (!empty($d->extracted_text)) {
                $this->line("  مقتطف من المحتوى: " . mb_substr($d->extracted_text, 0, 150) . "...");
            }
        }

        // Check WAHA session via WahaWhatsAppService
        $sessionName = $company->getWahaSessionName();
        $this->info("\n=== فحص جلسات WAHA WhatsApp ({$sessionName}) ===");
        $session = $waha->getSession($sessionName);

        if ($session) {
            $name = $session['name'] ?? $sessionName;
            $status = $session['status'] ?? 'unknown';
            $phone = $waha->getConnectedPhone($sessionName) ?? 'none';
            $pushName = $waha->getPushName($sessionName) ?? 'none';

            $this->line("- الجلسة [{$name}]: الحالة = {$status}");
            $this->line("- الرقم المتصل = {$phone}");
            $this->line("- اسم الحساب = {$pushName}");

            if ($waha->isWorking($sessionName)) {
                $this->info("✅ واتساب متصل وجاهز للرد الآلي الفوري!");
                if ($this->option('sync') || $company->whatsapp_session_status !== 'connected') {
                    try {
                        $company->update([
                            'whatsapp_session_status' => 'connected',
                            'whatsapp_number' => $phone,
                            'whatsapp_connected_at' => $company->whatsapp_connected_at ?? now(),
                        ]);
                        $this->info("🔄 تم تحديث ومزامنة حالة المنشأة إلى 'متصل ونشط' بالرقم ({$phone}) بنجاح.");
                    } catch (\Throwable $e) {
                        $this->warn("⚠️ تعذر مزامنة الرقم: " . $e->getMessage());
                    }
                }
            } else {
                $this->warn("⚠️ الجلسة ليست في حالة WORKING (الحالة الحالية: {$status})");
            }
        } else {
            $this->error("تعذر جلب تفاصيل الجلسة من WAHA للجلسة {$sessionName}");
        }
    }
}
