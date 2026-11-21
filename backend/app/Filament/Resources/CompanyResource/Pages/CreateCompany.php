<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\SystemNotification;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();

        if (! empty($data['admin_user_email'])) {
            User::updateOrCreate(
                ['email' => $data['admin_user_email']],
                [
                    'company_id' => $this->record->id,
                    'name' => $data['admin_user_name'] ?? ('مدير ' . $this->record->name),
                    'password' => Hash::make($data['admin_user_password'] ?? 'password123'),
                    'role' => 'company_admin',
                    'phone' => $this->record->phone ?? $this->record->whatsapp_number,
                    'status' => 'active',
                ]
            );
        }

        SystemNotification::create([
            'company_id' => $this->record->id,
            'title' => 'شركة جديدة',
            'message' => 'تم تسجيل المنشأة "' . $this->record->name . '" في المنصة وتحديد مدة الاشتراك بنجاح.',
            'type' => 'success',
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
