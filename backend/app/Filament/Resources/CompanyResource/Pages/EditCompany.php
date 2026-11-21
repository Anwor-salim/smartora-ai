<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $data = $this->form->getRawState();

        $managerUser = $this->record->users()->where('role', 'company_admin')->first();

        $userEmail = !empty($data['admin_user_email'])
            ? trim($data['admin_user_email'])
            : ($managerUser?->email ?? (!empty($this->record->email) ? trim($this->record->email) : ($this->record->slug . '@smartora.com')));

        $userName = !empty($data['admin_user_name'])
            ? trim($data['admin_user_name'])
            : ($managerUser?->name ?? ('مدير ' . $this->record->name));

        $userData = [
            'company_id' => $this->record->id,
            'name' => $userName,
            'role' => 'company_admin',
            'phone' => $this->record->phone ?? $this->record->whatsapp_number,
            'status' => 'active',
        ];

        if (!empty($data['admin_user_password'])) {
            $userData['password'] = Hash::make(trim($data['admin_user_password']));
        }

        if ($managerUser) {
            $userData['email'] = $userEmail;
            $managerUser->update($userData);
        } else {
            $userData['email'] = $userEmail;
            $userData['password'] = Hash::make(!empty($data['admin_user_password']) ? trim($data['admin_user_password']) : 'password123');
            User::create($userData);
        }

        Notification::make()
            ->title('تم حفظ بيانات المنشأة وحساب المدير بنجاح')
            ->body("حساب الدخول: {$userEmail}")
            ->success()
            ->send();
    }
}
