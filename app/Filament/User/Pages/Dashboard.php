<?php

namespace App\Filament\User\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        return '';
    }

    public static function getNavigationIcon(): ?string
    {
        return null;
    }
}
