<?php

namespace App\Filament\Company\Widgets;

use App\Models\Company;
use Filament\Widgets\Widget;

class OnboardingStepsWidget extends Widget
{
    protected static string $view = 'filament.company.widgets.onboarding-steps-widget';

    protected static ?int $sort = -1;

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
}
