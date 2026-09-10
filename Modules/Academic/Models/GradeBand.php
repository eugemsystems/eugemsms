<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\GradeBandFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book D ACA-05 §2/BR-ACA-05-002. Contiguity/non-overlap across every
 * sibling band of the same scale is enforced by
 * `CreateGradeBandAction` at save time, not here — see that action's
 * docblock.
 *
 * @property int $id
 * @property int $school_id
 * @property int $grading_scale_id
 * @property string $grade
 * @property string|null $descriptor
 * @property string $min_percent
 * @property string $max_percent
 * @property string|null $points
 * @property bool $is_pass
 * @property string|null $colour
 * @property int $sort_order
 */
class GradeBand extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GradeBandFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'grading_scale_id', 'grade', 'descriptor', 'min_percent', 'max_percent',
        'points', 'is_pass', 'colour', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_pass' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GradeBandFactory::new();
    }

    /**
     * @return BelongsTo<GradingScale, $this>
     */
    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }
}
