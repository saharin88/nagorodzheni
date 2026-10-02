<?php

namespace App\Models;

use Database\Factories\DecreeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $number
 * @property Carbon $date
 * @property string $url
 * @property bool $is_hero
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $awardees_count
 * @property-read int|null $posthumous_awardees_count
 */
#[Fillable(['number', 'date', 'url', 'is_hero'])]
class Decree extends Model
{
    /** @use HasFactory<DecreeFactory> */
    use HasFactory;

    /**
     * @return HasMany<Awardee, $this>
     */
    public function awardees(): HasMany
    {
        return $this->hasMany(Awardee::class);
    }

    /**
     * Order the decrees by the year and the ordinal encoded in the number column.
     *
     * The column is a string ("997/2026"), so a plain `orderBy` sorts it lexicographically
     * and places "1000/2026" after "997/2026". The year is compared first, because decree
     * numbering restarts every year.
     *
     * @param  Builder<Decree>  $query
     */
    #[Scope]
    protected function orderByNumber(Builder $query, string $direction = 'desc'): void
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        [$yearExpression, $ordinalExpression] = match (DB::connection()->getDriverName()) {
            'sqlite' => [
                "CAST(substr(number, instr(number || '/', '/') + 1) AS INTEGER)",
                "CAST(substr(number, 1, instr(number || '/', '/') - 1) AS INTEGER)",
            ],
            'pgsql' => [
                "CAST(NULLIF(split_part(number, '/', 2), '') AS INTEGER)",
                "CAST(NULLIF(split_part(number, '/', 1), '') AS INTEGER)",
            ],
            default => [
                "CAST(SUBSTRING_INDEX(number, '/', -1) AS UNSIGNED)",
                "CAST(SUBSTRING_INDEX(number, '/', 1) AS UNSIGNED)",
            ],
        };

        $query->orderByRaw("{$yearExpression} {$direction}, {$ordinalExpression} {$direction}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_hero' => 'boolean',
        ];
    }
}
