<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Blocks every mutating resource ability, so a panel that lists records cannot
 * create, edit or delete them even if a mutation action is added later.
 */
trait IsReadOnlyResource
{
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
