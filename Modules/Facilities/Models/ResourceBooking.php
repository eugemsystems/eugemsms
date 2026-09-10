<?php

declare(strict_types=1);

namespace Modules\Facilities\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Facilities\Database\Factories\ResourceBookingFactory;
use Modules\Operations\Models\WorkOrder;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;

/**
 * Book H2 OPS-05 §2/BR-OPS-05-001/002/007 — guarded by `PeriodGuard`
 * manually, the same convention every financial-adjacent model in
 * this codebase uses.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $booking_number
 * @property int $resource_id
 * @property string $booking_type
 * @property string $purpose
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $setup_from
 * @property Carbon|null $cleanup_until
 * @property int|null $expected_attendance
 * @property int|null $requested_by_staff_id
 * @property int|null $department_id
 * @property string|null $hirer_name
 * @property string|null $hirer_contact
 * @property string|null $hirer_organisation
 * @property int|null $hire_amount_minor
 * @property int|null $deposit_amount_minor
 * @property int|null $deposit_receipt_id
 * @property int|null $invoice_id
 * @property int|null $contract_file_id
 * @property bool|null $deposit_refunded
 * @property int|null $damage_deducted_minor
 * @property string $status
 * @property int|null $approval_request_id
 * @property string|null $recurrence_rule
 * @property int|null $parent_booking_id
 * @property int|null $setup_work_order_id
 * @property int|null $cleanup_work_order_id
 * @property string|null $condition_before_notes
 * @property string|null $condition_after_notes
 * @property string|null $cancellation_reason
 */
class ResourceBooking extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ResourceBookingFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'booking_number', 'resource_id', 'booking_type', 'purpose', 'starts_at',
        'ends_at', 'setup_from', 'cleanup_until', 'expected_attendance', 'requested_by_staff_id',
        'department_id', 'hirer_name', 'hirer_contact', 'hirer_organisation', 'hire_amount_minor',
        'deposit_amount_minor', 'deposit_receipt_id', 'invoice_id', 'contract_file_id', 'deposit_refunded',
        'damage_deducted_minor', 'status', 'cancellation_reason', 'approval_request_id', 'recurrence_rule',
        'parent_booking_id', 'setup_work_order_id', 'cleanup_work_order_id', 'condition_before_notes',
        'condition_after_notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'setup_from' => 'datetime',
            'cleanup_until' => 'datetime',
            'deposit_refunded' => 'boolean',
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
        return ResourceBookingFactory::new();
    }

    /**
     * @return BelongsTo<BookableResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResource::class, 'resource_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function requestedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requested_by_staff_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parentBooking(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_booking_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function setupWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'setup_work_order_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function cleanupWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'cleanup_work_order_id');
    }
}
