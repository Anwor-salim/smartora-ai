<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\SystemNotification;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();

        // 1. Determine Company Admin Email (from Tab 3, or company email in Tab 1, or slug@smartora.com)
        $userEmail = !empty($data['admin_user_email'])
            ? trim($data['admin_user_email'])
            : (!empty($this->record->email) ? trim($this->record->email) : ($this->record->slug . '@smartora.com'));

        $userName = !empty($data['admin_user_name'])
            ? trim($data['admin_user_name'])
            : ('مدير ' . $this->record->name);

        $passwordPlain = !empty($data['admin_user_password']) ? trim($data['admin_user_password']) : 'password123';

        // 2. Guarantee Company Admin User Creation
        $user = User::updateOrCreate(
            ['email' => $userEmail],
            [
                'company_id' => $this->record->id,
                'name' => $userName,
                'password' => Hash::make($passwordPlain),
                'role' => 'company_admin',
                'phone' => $this->record->phone ?? $this->record->whatsapp_number,
                'status' => 'active',
            ]
        );

        // 3. System Notification for Admin
        SystemNotification::create([
            'company_id' => $this->record->id,
            'title' => 'شركة جديدة وحساب دخول',
            'message' => 'تم تسجيل المنشأة "' . $this->record->name . '" وإنشاء حساب الدخول للمدير (' . $userEmail . ') بنجاح.',
            'type' => 'success',
        ]);

        // 4. Alert Admin with Login Credentials
        Notification::make()
            ->title('تم إنشاء الشركة وحساب الدخول بنجاح 🎉')
            ->body("بيانات تسجيل الدخول للشركة:\n• البريد الإلكتروني: {$userEmail}\n• كلمة المرور: {$passwordPlain}\n• رابط الدخول: " . url('/company/login'))
            ->success()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
