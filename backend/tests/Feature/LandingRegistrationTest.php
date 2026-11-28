<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_displays_plans_pricing_and_registration_form(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('SmartOra');
        $response->assertSee('الباقة التجريبية');
        $response->assertSee('شهر مجاناً');
        $response->assertSee('$13');
        $response->assertSee('$12');
        $response->assertSee('$11');
        $response->assertSee('الأسعار قابلة للنقاش');
        $response->assertSee('بانتظار تفعيل الأدمن');
    }

    public function test_register_page_renders_successfully(): void
    {
        $response = $this->get('/register?plan=6_months');

        $response->assertStatus(200);
        $response->assertSee('إنشاء حساب منشأة');
    }

    public function test_company_registration_creates_pending_company_and_blocks_login_until_admin_approves(): void
    {
        $response = $this->post('/register', [
            'company_name' => 'مؤسسة الأفق الحديثة',
            'owner_name' => 'خالد صالح',
            'email' => 'khaled@alofoq.com',
            'phone' => '967771234567',
            'whatsapp_number' => '',
            'subscription_plan' => '6_months',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'notes' => 'نرغب بمناقشة خصم إضافي لفرعين',
        ]);

        $response->assertRedirect(url('/#register'));
        $response->assertSessionHas('registration_success');

        $company = Company::where('email', 'khaled@alofoq.com')->first();
        $this->assertNotNull($company);
        $this->assertSame('pending', $company->status);
        $this->assertSame('6_months', $company->subscription_plan);
        $this->assertNull($company->whatsapp_number);
        $this->assertFalse($company->isSubscriptionActive());

        $user = User::where('email', 'khaled@alofoq.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('company_admin', $user->role);
        $this->assertSame('inactive', $user->status);
        $this->assertSame($company->id, $user->company_id);

        $this->postJson('/api/auth/login', [
            'email' => 'khaled@alofoq.com',
            'password' => 'secret123',
        ])->assertStatus(403);

        $notification = SystemNotification::where('company_id', $company->id)->first();
        $this->assertNotNull($notification);
        $this->assertFalse((bool) $notification->is_read);

        // Simulate Admin approving and activating the company
        $company->update([
            'status' => 'active',
            'subscription_starts_at' => now(),
            'subscription_expires_at' => now()->addMonths(6),
        ]);
        $user->update(['status' => 'active']);

        $company->refresh();
        $user->refresh();

        $this->assertTrue($company->isSubscriptionActive());

        $this->postJson('/api/auth/login', [
            'email' => 'khaled@alofoq.com',
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'company' => ['id', 'name']]]);
    }

    public function test_duplicate_email_or_whatsapp_number_is_rejected(): void
    {
        Company::create([
            'name' => 'شركة قائمة',
            'slug' => 'existing-company',
            'whatsapp_number' => '967770001112',
            'status' => 'active',
            'subscription_plan' => '3_months',
        ]);

        User::create([
            'name' => 'مدير حالي',
            'email' => 'taken@company.com',
            'password' => bcrypt('password123'),
            'role' => 'company_admin',
            'status' => 'active',
        ]);

        $resEmail = $this->post('/register', [
            'company_name' => 'شركة جديدة',
            'owner_name' => 'مدير جديد',
            'email' => 'taken@company.com',
            'phone' => '967770009999',
            'subscription_plan' => 'free_trial',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);
        $resEmail->assertSessionHasErrors('email');

        $resWhatsapp = $this->post('/register', [
            'company_name' => 'شركة ثانية',
            'owner_name' => 'مدير ثاني',
            'email' => 'new@company.com',
            'phone' => '967770009998',
            'whatsapp_number' => '+967 770 001 112',
            'subscription_plan' => 'yearly',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);
        $resWhatsapp->assertSessionHasErrors('whatsapp_number');
    }
}
