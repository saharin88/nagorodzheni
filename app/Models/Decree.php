<?php

namespace App\Models;

use Database\Factories\DecreeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property Carbon $date
 * @property string $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $awardees_count
 * @property-read int|null $posthumous_awardees_count
 */
#[Fillable(['number', 'date', 'url'])]
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
