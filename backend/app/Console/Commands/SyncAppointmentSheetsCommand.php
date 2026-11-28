<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AppointmentBookingService;
use Illuminate\Console\Command;

class SyncAppointmentSheetsCommand extends Command
{
    protected $signature = 'appointments:sync-sheets {company_id?}';
    protected $description = 'Sync appointment statuses and column headers from connected Google Sheets';

    public function handle(AppointmentBookingService $bookingService): int
    {
        $companyId = $this->argument('company_id');

        $companies = $companyId
            ? Company::where('id', (int) $companyId)->get()
            : Company::where('status', 'active')->get();

        $totalApproved = 0;
        $totalRejected = 0;
        $totalCancelled = 0;

        foreach ($companies as $company) {
            $settings = is_array($company->settings) ? $company->settings : [];
            if (empty($settings['booking_enabled'])) {
                continue;
            }
            if (empty($settings['booking_sheet_id']) && empty($settings['booking_sheet_url'])) {
                continue;
            }

            $stats = $bookingService->syncCompanySheetStatuses($company);
            $totalApproved += (int) ($stats['approved'] ?? 0);
            $totalRejected += (int) ($stats['rejected'] ?? 0);
            $totalCancelled += (int) ($stats['cancelled'] ?? 0);
        }

        $this->info("Synced appointments: approved={$totalApproved}, rejected={$totalRejected}, cancelled={$totalCancelled}");

        return 0;
    }
}
