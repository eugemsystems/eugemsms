<?php

declare(strict_types=1);

namespace Modules\Sport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\House;
use Modules\Sport\Database\Factories\HousePointFactory;

/**
 * Book H2 OPS-07 §2. See the owning migration's docblock for
 * `source_type`'s real values and where each is written from.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $house_id
 * @property int|null $competition_id
 * @property string $source_type
 * @property int|null $source_id
 * @property float $points
 * @property string|null $reason
 * @property Carbon $awarded_at
 * @property int|null $awarded_by
 */
class HousePoint extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HousePointFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'house_id', 'competition_id',
        'source_type', 'source_id', 'points', 'reason', 'awarded_at', 'awarded_by',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'awarded_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HousePointFactory::new();
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /**
     * @return BelongsTo<HouseCompetition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(HouseCompetition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
