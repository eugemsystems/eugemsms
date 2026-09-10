<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Database\Factories\StaffContractFactory;

/**
 * Book C PPL-04 §2/BR-PPL-04-002. A staff member has at most one
 * active contract at a time — enforced by `CreateStaffContractAction`/
 * `RenewStaffContractAction`, not by a DB constraint (the "one active"
 * rule is time-independent of any single column). Immutable once
 * created except for the lifecycle columns a status transition owns.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $staff_id
 * @property string $contract_type
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $probation_months
 * @property int $notice_period_days
 * @property int|null $basic_salary_minor
 * @property string|null $salary_currency
 * @property string $status
 * @property int|null $renewed_to_contract_id
 * @property Carbon|null $terminated_on
 * @property string|null $termination_reason
 */
class StaffContract extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffContractFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = [
        'status', 'ends_on', 'renewed_to_contract_id', 'terminated_on', 'termination_reason', 'approved_by',
    ];

    protected $fillable = [
        'school_id', 'staff_id', 'contract_type', 'starts_on', 'ends_on', 'probation_months',
        'notice_period_days', 'weekly_hours', 'basic_salary_minor', 'salary_currency', 'salary_grade',
        'salary_notch', 'contract_document_id', 'signed_on', 'status', 'renewed_to_contract_id',
        'terminated_on', 'termination_reason', 'created_by', 'approved_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'basic_salary_minor' => 'encrypted',
            'signed_on' => 'date',
            'terminated_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffContractFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $notAllowed = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($notAllowed !== []) {
                throw new InvalidStateTransitionException(
                    'Only status, ends_on, renewed_to_contract_id, terminated_on, termination_reason, and approved_by may change on an existing contract.',
                    ['dirty' => $notAllowed],
                );
            }
        });
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<StaffContract, $this>
     */
    public function renewedTo(): BelongsTo
    {
        return $this->belongsTo(StaffContract::class, 'renewed_to_contract_id');
    }
}
