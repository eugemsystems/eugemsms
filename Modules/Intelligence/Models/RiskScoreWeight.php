<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\RiskScoreWeightFactory;

/**
 * Book J INT-03 §2/BR-INT-03-003.
 *
 * @property int $id
 * @property int $school_id
 * @property string $indicator_key
 * @property float $weight
 * @property bool $is_enabled
 */
class RiskScoreWeight extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RiskScoreWeightFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'indicator_key', 'weight', 'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RiskScoreWeightFactory::new();
    }

    /**
     * @return BelongsTo<RiskIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(RiskIndicator::class, 'indicator_key', 'key');
    }
}
