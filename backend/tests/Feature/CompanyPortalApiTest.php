<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyPortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('GEMINI_API_KEY=');
        putenv('OPENAI_API_KEY=');
    }

    protected function createCompanyAdmin(): array
    {
        $company = Company::create([
            'name' => 'عيادة الدكتور أحمد الكسادي',
            'slug' => 'ksadi-clinic-' . uniqid(),
            'phone' => '771122334',
            'whatsapp_number' => '967771122334',
            'support_phone' => '967779990001',
            'address' => 'المكلا - الشرج',
            'working_hours' => '9 صباحاً - 10 مساءً',
            'status' => 'active',
            'whatsapp_session_status' => 'connected',
            'subscription_plan' => 'monthly_pro',
            'subscription_starts_at' => now()->subDay(),
            'subscription_expires_at' => now()->addMonth(),
            'settings' => [
                'greeting_message' => 'أهلاً بك في عيادة الدكتور أحمد الكسادي!',
                'booking_enabled' => true,
            ],
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'مدير العيادة',
            'email' => 'admin@ksadi.com',
            'password' => bcrypt('password123'),
            'role' => 'company_admin',
            'status' => 'active',
        ]);

        return [$company, $user];
    }

    public function test_dashboard_and_profile_endpoints_return_full_company_data(): void
    {
        [$company, $user] = $this->createCompanyAdmin();
        Sanctum::actingAs($user);

        $conv = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'whatsapp',
            'customer_phone' => '967771314738',
            'customer_name' => 'أنور سالم',
            'last_message' => 'أبغى أحجز موعد',
            'conversation_state' => 'booking',
            'last_interaction_at' => now(),
        ]);
        Message::create([
            'conversation_id' => $conv->id,
            'sender' => 'customer',
            'message' => 'أبغى أحجز موعد',
        ]);

        Appointment::create([
            'company_id' => $company->id,
            'conversation_id' => $conv->id,
            'booking_code' => 'BK-API-01',
            'channel' => 'whatsapp',
            'customer_phone' => '967771314738',
            'customer_name' => 'أنور سالم',
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => 'العصر',
            'queue_number' => 1,
            'status' => 'pending',
        ]);

        $dash = $this->getJson('/api/company/dashboard?range=all');
        $dash->assertOk()
            ->assertJsonPath('company.name', 'عيادة الدكتور أحمد الكسادي')
            ->assertJsonPath('kpis.total_appointments', 1)
            ->assertJsonPath('kpis.pending_appointments', 1)
            ->assertJsonPath('kpis.total_conversations', 1)
            ->assertJsonPath('kpis.connected_channels', 1);

        $updateRes = $this->putJson('/api/company/profile', [
            'name' => 'عيادة الطب الباطني',
            'about_us' => 'عيادة متخصصة في الطب الباطني',
            'services_offered' => 'تشخيص ومتابعة الأمراض المزمنة',
            'working_hours' => '9 صباحاً - 10 مساءً',
            'response_tone' => 'concise',
            'memory_messages_limit' => 10,
            'timezone' => 'Asia/Aden',
            'booking_enabled' => true,
        ]);

        $updateRes->assertOk();

        $profile = $this->getJson('/api/company/profile');
        $profile->assertOk()
            ->assertJsonPath('company.name', 'عيادة الطب الباطني')
            ->assertJsonPath('company.settings.response_tone', 'concise')
            ->assertJsonPath('company.settings.memory_messages_limit', 10)
            ->assertJsonPath('company.settings.timezone', 'Asia/Aden')
            ->assertJsonPath('company.settings.booking_enabled', true);
    }

    public function test_documents_appointments_booking_settings_conversations_and_simulator_apis(): void
    {
        Storage::fake('local');
        Http::fake(['*/api/sendText' => Http::response(['id' => 'msg_ok'], 200)]);

        [$company, $user] = $this->createCompanyAdmin();
        Sanctum::actingAs($user);

        // 1. Document upload, list, reparse, delete
        $csvFile = UploadedFile::fake()->createWithContent('clinics.csv', "القسم,السعر\nعيادة الباطنية,5000\n");
        $uploadRes = $this->post('/api/company/documents/upload', [
            'file' => $csvFile,
            'title' => 'قائمة العيادات',
        ]);
        $uploadRes->assertCreated();
        $docId = $uploadRes->json('document.id');

        $this->getJson('/api/company/documents')
            ->assertOk()
            ->assertJsonCount(1, 'documents');

        $this->postJson("/api/company/documents/{$docId}/reparse")
            ->assertOk();

        $this->deleteJson("/api/company/documents/{$docId}")
            ->assertOk();
        $this->assertSame(0, KnowledgeDocument::where('company_id', $company->id)->count());

        // 2. Booking settings
        $this->putJson('/api/company/booking-settings', [
            'booking_enabled' => true,
            'booking_approval_mode' => 'reception_approval',
            'booking_notify_reception_whatsapp' => false,
            'booking_daily_limit' => 15,
            'booking_working_days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
            'booking_date_overrides' => ['2026-12-05' => 0],
            'booking_sheet_url' => 'https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUvWxYz12345/edit',
            'booking_sheet_tab' => 'الحجوزات',
            'booking_custom_fields' => 'الاسم، العمر، التاريخ، الوقت',
        ])->assertOk()
            ->assertJsonPath('booking_sheet_id', '1AbCdEfGhIjKlMnOpQrStUvWxYz12345');

        $this->getJson('/api/company/booking-settings')
            ->assertOk()
            ->assertJsonPath('booking_daily_limit', 15)
            ->assertJsonPath('booking_sheet_tab', 'الحجوزات');

        // 3. Appointments list & actions
        $appt = Appointment::create([
            'company_id' => $company->id,
            'booking_code' => 'BK-PORTAL-01',
            'channel' => 'whatsapp',
            'customer_phone' => '967770001112',
            'customer_name' => 'خالد صالح',
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => 'العصر',
            'queue_number' => 1,
            'status' => 'pending',
        ]);

        $this->getJson('/api/company/appointments?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'appointments')
            ->assertJsonPath('counts.pending', 1);

        $this->postJson("/api/company/appointments/{$appt->id}/approve")
            ->assertOk();
        $this->assertSame('approved', $appt->fresh()->status);

        $this->postJson("/api/company/appointments/{$appt->id}/reject", ['reason' => 'ممتلئ'])
            ->assertOk();
        $this->assertSame('rejected', $appt->fresh()->status);

        // 4. Conversations pause & reset
        $conv = Conversation::create([
            'company_id' => $company->id,
            'channel' => 'whatsapp',
            'customer_phone' => '967770001112',
            'customer_name' => 'خالد صالح',
            'last_message' => 'مرحبا',
            'conversation_state' => 'inquiring',
            'summary' => 'ملخص قديم',
            'last_interaction_at' => now(),
        ]);

        $this->getJson('/api/company/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'conversations');

        $this->postJson("/api/company/conversations/{$conv->id}/toggle-pause")
            ->assertOk()
            ->assertJsonPath('ai_paused', true);

        $this->postJson("/api/company/conversations/{$conv->id}/reset-context")
            ->assertOk();
        $this->assertNull($conv->fresh()->summary);

        // 5. Simulator send & reset
        $this->postJson('/api/company/simulator/send', [
            'message' => 'مرحبا',
        ])->assertOk()->assertJsonStructure(['reply', 'time']);

        $this->postJson('/api/company/simulator/reset')
            ->assertOk()
            ->assertJsonStructure(['greeting', 'time']);
    }
}
