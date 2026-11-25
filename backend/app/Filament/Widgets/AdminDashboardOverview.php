<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Conversation;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class AdminDashboardOverview extends Widget
{
    protected static string $view = 'filament.widgets.admin-dashboard-overview';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function getCompanies(): Collection
    {
        return Company::with(['users' => fn ($q) => $q->where('role', 'company_admin')])
            ->withCount(['documents', 'conversations'])
            ->latest()
            ->limit(6)
            ->get();
    }

    public function getRecentConversations(): Collection
    {
        return Conversation::with('company')
            ->withCount('messages')
            ->latest('last_interaction_at')
            ->limit(5)
            ->get();
    }
}
