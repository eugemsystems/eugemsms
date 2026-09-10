<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Database\Factories\DutyAssignmentFactory;

/**
 * Book C PPL-04 §2/BR-PPL-04-016 ⭐ — a swap never overwrites the
 * original assignment: `SwapDutyAssignmentAction` sets this row's own
 * `status` to `swapped` and creates a NEW row for the staff member
 * taking it over, so "both original and swapped assignments remain
 * visible" holds literally.
 *
 * @property int $id
 * @property int $school_id
 * @property int $roster_id
 * @property int $staff_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $status
 * @property int|null $swapped_with_staff_id
 * @property int|null $swap_approved_by
 * @property string|null $notes
 */
class DutyAssignment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DutyAssignmentFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = ['status', 'swapped_with_staff_id', 'swap_approved_by', 'notes'];

    protected $fillable = [
        'school_id', 'roster_id', 'staff_id', 'starts_at', 'ends_at', 'status',
        'swapped_with_staff_id', 'swap_approved_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DutyAssignmentFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $notAllowed = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($notAllowed !== []) {
                throw new InvalidStateTransitionException(
                    'Only status, swapped_with_staff_id, swap_approved_by, and notes may change on an existing duty assignment.',
                    ['dirty' => $notAllowed],
                );
            }
        });
    }

    /**
     * @return BelongsTo<DutyRoster, $this>
     */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(DutyRoster::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
