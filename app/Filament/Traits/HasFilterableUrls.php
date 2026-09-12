<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasFilterableUrls
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function getFilteredIndexUrl(array $filters): string
    {
        $queryFilters = [];

        foreach ($filters as $filterName => $value) {
            if (is_array($value) || $value instanceof Collection) {
                $valuesArray = $value instanceof Collection
                    ? $value->map(fn ($item) => $item instanceof Model ? $item->getKey() : $item)->toArray()
                    : $value;

                $queryFilters[$filterName] = ['values' => $valuesArray];
            } elseif (is_bool($value)) {
                $queryFilters[$filterName] = ['isActive' => $value];
            } else {
                $id = $value instanceof Model ? $value->getKey() : $value;
                $queryFilters[$filterName] = ['value' => $id];
            }
        }

        return static::getUrl('index', [
            'filters' => $queryFilters,
        ]);
    }
}
