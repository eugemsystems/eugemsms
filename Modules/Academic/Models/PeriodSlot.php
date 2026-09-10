<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\PeriodSlotFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book E ACA-03 §2/BR-ACA-03-003.
 *
 * @property int $id
 * @property int $school_id
 * @property int $structure_id
 * @property int $cycle_day
 * @property int $period_number
 * @property string $label
 * @property string $slot_type
 * @property string $starts_at
 * @property string $ends_at
 * @property int $duration_minutes
 * @property bool $is_teachable
 * @property bool $requires_attendance
 * @property int|null $sort_order
 */
class PeriodSlot extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PeriodSlotFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'structure_id', 'cycle_day', 'period_number', 'label', 'slot_type',
        'starts_at', 'ends_at', 'duration_minutes', 'is_teachable', 'requires_attendance', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_teachable' => 'boolean',
            'requires_attendance' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PeriodSlotFactory::new();
    }

    /**
     * @return BelongsTo<PeriodStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(PeriodStructure::class, 'structure_id');
    }
}
