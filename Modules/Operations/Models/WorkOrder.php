<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

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
use Modules\Operations\Database\Factories\WorkOrderFactory;
use Modules\People\Models\Staff;
use Modules\Stores\Models\Supplier;

/**
 * Book H2 OPS-02 §2/BR-OPS-02-004/008 — guarded by `PeriodGuard`
 * manually, the same established convention every financial-adjacent
 * model in this codebase uses (see `Modules\Stores\Models\StoreRequisition`'s
 * own docblock for why the `BelongsToSession` trait isn't used).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $work_order_number
 * @property int|null $maintenance_asset_id
 * @property int|null $fault_report_id
 * @property int|null $schedule_id
 * @property string $work_type
 * @property string $title
 * @property string $priority
 * @property string $assigned_team
 * @property int|null $assigned_staff_id
 * @property int|null $contractor_supplier_id
 * @property int $cost_centre_id
 * @property int|null $budget_line_id
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $target_completion
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string $status
 * @property int|null $approved_by
 * @property float $labour_hours
 * @property int $labour_cost_minor
 * @property int $parts_cost_minor
 * @property int $contractor_cost_minor
 * @property int $total_cost_minor
 * @property string $currency
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property bool|null $sla_met
 * @property int $raised_by
 */
class WorkOrder extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'work_order_number', 'maintenance_asset_id',
        'fault_report_id', 'schedule_id', 'work_type', 'title', 'description', 'location', 'priority',
        'assigned_team', 'assigned_staff_id', 'contractor_supplier_id', 'cost_centre_id', 'budget_line_id',
        'scheduled_for', 'target_completion', 'started_at', 'completed_at', 'status', 'approval_request_id',
        'approved_by', 'labour_hours', 'labour_cost_minor', 'parts_cost_minor', 'contractor_cost_minor', 'total_cost_minor',
        'currency', 'completion_notes', 'completion_photo_ids', 'verified_by', 'verified_at', 'sla_met',
        'raised_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'target_completion' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'labour_hours' => 'decimal:2',
            'completion_photo_ids' => 'array',
            'verified_at' => 'datetime',
            'sla_met' => 'boolean',
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
        return WorkOrderFactory::new();
    }

    /**
     * @return BelongsTo<MaintenanceAsset, $this>
     */
    public function maintenanceAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class);
    }

    /**
     * @return BelongsTo<FaultReport, $this>
     */
    public function faultReport(): BelongsTo
    {
        return $this->belongsTo(FaultReport::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function contractorSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'contractor_supplier_id');
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
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    /**
     * @return HasMany<WorkOrderPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }

    /**
     * @return HasMany<WorkOrderLabour, $this>
     */
    public function labour(): HasMany
    {
        return $this->hasMany(WorkOrderLabour::class);
    }
}
