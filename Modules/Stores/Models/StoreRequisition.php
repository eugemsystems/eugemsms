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
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\Journal;
use Modules\People\Models\Department;
use Modules\Stores\Database\Factories\StoreRequisitionFactory;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-025 — obeys `PeriodGuard` like every
 * other financial write, guarded the same way
 * `Modules\Finance\Models\Invoice` guards itself (a direct call from
 * `booted()`, not the unused `BelongsToSession` trait — no other
 * model in this codebase uses that trait, since its ambient
 * `SessionContext` auto-fill doesn't fit Actions that resolve their
 * own `academic_year_id`/`term_id` from an explicit DTO).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $requisition_number
 * @property int $store_id
 * @property int|null $requesting_department_id
 * @property int $cost_centre_id
 * @property string $purpose
 * @property Carbon|null $required_by
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string $status
 * @property int|null $approval_request_id
 * @property int $requested_by
 * @property int|null $approved_by
 * @property int|null $issued_by
 * @property Carbon|null $issued_at
 * @property int|null $received_by
 * @property int|null $total_cost_minor
 * @property string $currency
 * @property int|null $journal_id
 */
class StoreRequisition extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StoreRequisitionFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'requisition_number', 'store_id',
        'requesting_department_id', 'cost_centre_id', 'purpose', 'required_by', 'source_type', 'source_id',
        'status', 'approval_request_id', 'requested_by', 'approved_by', 'issued_by', 'issued_at',
        'received_by', 'total_cost_minor', 'currency', 'journal_id',
    ];

    protected function casts(): array
    {
        return [
            'required_by' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            PeriodGuard::assertWritable($model);
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('status') && in_array($model->status, ['issued', 'partially_issued'], true)) {
                PeriodGuard::assertWritable($model);
            }
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StoreRequisitionFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function requestingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requesting_department_id');
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
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return HasMany<StoreRequisitionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StoreRequisitionLine::class, 'requisition_id');
    }

    public function throwIfNotOpenForIssue(): void
    {
        if (! in_array($this->status, ['approved', 'partially_issued'], true)) {
            throw new InvalidStateTransitionException(
                "Requisition #{$this->id} must be approved to issue (currently {$this->status}).",
                ['requisition_id' => $this->id, 'status' => $this->status],
            );
        }
    }
}
