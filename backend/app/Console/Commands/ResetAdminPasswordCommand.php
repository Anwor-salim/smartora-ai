<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPasswordCommand extends Command
{
    protected $signature = 'admin:credentials {email=admin@smartora.com} {password=password123}';
    protected $description = 'Set or reset Super Admin credentials';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'أنور سالم (السوبر أدمن)',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );

        $this->info("=== بيانات حساب السوبر أدمن ===");
        $this->line("البريد الإلكتروني: {$user->email}");
        $this->line("كلمة المرور: {$password}");
        $this->line("الرتبة: {$user->role}");
        $this->line("الاسم: {$user->name}");
        $this->line("الحالة: {$user->status}");

        return 0;
    }
}
