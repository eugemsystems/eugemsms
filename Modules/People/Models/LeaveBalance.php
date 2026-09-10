<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\LeaveBalanceFactory;

/**
 * Book C PPL-04 §2/BR-PPL-04-011. `available_days` is maintained
 * directly by the leave-request lifecycle — never double-counted, per
 * AC-PPL-04-004.
 *
 * @property int $id
 * @property int $school_id
 * @property int $staff_id
 * @property int $leave_type_id
 * @property int $academic_year_id
 * @property string $entitlement_days
 * @property string $accrued_days
 * @property string $carried_forward_days
 * @property string $taken_days
 * @property string $pending_days
 * @property string $available_days
 */
class LeaveBalance extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LeaveBalanceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'staff_id', 'leave_type_id', 'academic_year_id', 'entitlement_days', 'accrued_days',
        'carried_forward_days', 'taken_days', 'pending_days', 'available_days',
    ];

    protected function casts(): array
    {
        return [
            'entitlement_days' => 'decimal:1',
            'accrued_days' => 'decimal:1',
            'carried_forward_days' => 'decimal:1',
            'taken_days' => 'decimal:1',
            'pending_days' => 'decimal:1',
            'available_days' => 'decimal:1',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LeaveBalanceFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
