<?php

namespace App\Models;

use Database\Factories\AwardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Award extends Model
{
    /** @use HasFactory<AwardFactory> */
    use HasFactory;

    /**
     * @return HasMany<Awardee, $this>
     */
    public function awardees(): HasMany
    {
        return $this->hasMany(Awardee::class);
    }
}
