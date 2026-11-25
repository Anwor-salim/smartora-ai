<?php

namespace App\Filament\Company\Widgets;

use App\Models\Company;
use Filament\Widgets\Widget;

class OnboardingStepsWidget extends Widget
{
    protected static string $view = 'filament.company.widgets.onboarding-steps-widget';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public static function resolveCompany(): ?Company
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

    public static function canView(): bool
    {
        $company = static::resolveCompany();
        if (! $company) {
            return false;
        }

        $progress = $company->getOnboardingProgress();

        return ! ($progress['is_fully_onboarded'] ?? false);
    }

    public function getCompany(): ?Company
    {
        return static::resolveCompany();
    }
}
