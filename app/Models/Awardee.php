<?php

namespace App\Models;

use Database\Factories\AwardeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $decree_id
 * @property int $award_id
 * @property string $full_name
 * @property string $rank
 * @property bool $is_posthumous
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Decree $decree
 * @property-read Award $award
 */
#[Fillable(['decree_id', 'award_id', 'full_name', 'rank', 'is_posthumous'])]
class Awardee extends Model
{
    /** @use HasFactory<AwardeeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Decree, $this>
     */
    public function decree(): BelongsTo
    {
        return $this->belongsTo(Decree::class);
    }

    /**
     * @return BelongsTo<Award, $this>
     */
    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_posthumous' => 'boolean',
        ];
    }
}
