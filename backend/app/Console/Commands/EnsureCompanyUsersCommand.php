<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class EnsureCompanyUsersCommand extends Command
{
    protected $signature = 'companies:ensure-users';
    protected $description = 'Ensure every company has an active company_admin user for login';

    public function handle(): int
    {
        $companies = Company::all();
        $this->info("Checking {$companies->count()} companies...");

        foreach ($companies as $company) {
            $existing = $company->users()->where('role', 'company_admin')->first();
            $email = $existing?->email ?? $company->email ?? ($company->slug . '@smartora.com');

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $existing?->name ?? ('مدير ' . $company->name),
                    'password' => $existing ? $existing->password : Hash::make('password123'),
                    'role' => 'company_admin',
                    'company_id' => $company->id,
                    'phone' => $company->phone ?? $company->whatsapp_number,
                    'status' => 'active',
                ]
            );

            $this->line("Company: {$company->name} -> User: {$user->email} (ID: {$user->id})");
        }

        $this->info("All companies now have active login accounts!");
        return 0;
    }
}
