<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StaffWorkloadFactory;

/**
 * Book C PPL-04 §3 ⭐ — derived cache, per term. Written only by
 * `RecalculateStaffWorkloadAction`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $staff_id
 * @property int $term_id
 * @property int $teaching_periods
 * @property int $class_teacher_count
 * @property int $duty_count
 * @property int $subject_count
 * @property int $class_count
 * @property int $learner_count
 * @property string|null $utilisation_percent
 * @property bool $is_overloaded
 * @property Carbon $recalculated_at
 */
class StaffWorkload extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffWorkloadFactory> */
    use HasFactory;

    /**
     * The spec's table name is singular (`staff_workload`), unlike
     * Eloquent's default pluralisation guess (`staff_workloads`).
     */
    protected $table = 'staff_workload';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'staff_id', 'term_id', 'teaching_periods', 'class_teacher_count', 'duty_count',
        'subject_count', 'class_count', 'learner_count', 'utilisation_percent', 'is_overloaded', 'recalculated_at',
    ];

    protected function casts(): array
    {
        return [
            'utilisation_percent' => 'decimal:2',
            'is_overloaded' => 'boolean',
            'recalculated_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffWorkloadFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
