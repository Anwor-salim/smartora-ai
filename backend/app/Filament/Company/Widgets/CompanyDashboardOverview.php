<?php

namespace App\Filament\Company\Widgets;

use App\Models\Company;
use App\Models\Conversation;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class CompanyDashboardOverview extends Widget
{
    protected static string $view = 'filament.company.widgets.company-dashboard-overview';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function getCompany(): ?Company
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        if ($user->isCompanyAdmin()) {
            return $user->company;
        }

        return $user->company ?? Company::first();
    }

    public function getRecentConversations(): Collection
    {
        $company = $this->getCompany();
        if (! $company) {
            return collect();
        }

        return Conversation::where('company_id', $company->id)
            ->withCount('messages')
            ->latest('last_interaction_at')
            ->limit(5)
            ->get();
    }
}
