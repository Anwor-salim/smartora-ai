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
        return auth()->user()?->company ?? Company::first();
    }
}
