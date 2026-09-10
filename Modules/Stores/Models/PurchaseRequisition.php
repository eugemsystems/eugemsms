<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Department;
use Modules\Stores\Database\Factories\PurchaseRequisitionFactory;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-006/025 — guarded by `PeriodGuard` the
 * same manual way `Modules\Stores\Models\StoreRequisition` is (see its
 * own docblock for why the `BelongsToSession` trait isn't used).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $requisition_number
 * @property int $department_id
 * @property int $cost_centre_id
 * @property int|null $budget_line_id
 * @property string $justification
 * @property Carbon|null $required_by
 * @property string $urgency
 * @property int $estimated_total_minor
 * @property string $currency
 * @property int|null $budget_available_minor
 * @property string|null $budget_check_result
 * @property string $status
 * @property int $requested_by
 * @property int|null $approved_by
 * @property string|null $rejection_reason
 * @property string|null $source_type
 * @property int|null $source_id
 */
class PurchaseRequisition extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PurchaseRequisitionFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'requisition_number', 'department_id', 'cost_centre_id',
        'budget_line_id', 'justification', 'required_by', 'urgency', 'estimated_total_minor', 'currency',
        'budget_available_minor', 'budget_check_result', 'status', 'approval_request_id', 'requested_by',
        'approved_by', 'rejection_reason', 'source_type', 'source_id',
    ];

    protected function casts(): array
    {
        return [
            'required_by' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            PeriodGuard::assertWritable($model);
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PurchaseRequisitionFactory::new();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<PurchaseRequisitionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class, 'requisition_id');
    }
}
