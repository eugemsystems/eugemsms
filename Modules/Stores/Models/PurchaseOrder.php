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
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Database\Factories\PurchaseOrderFactory;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-009/010/011 ⭐ — guarded by `PeriodGuard`
 * manually, matching `PurchaseRequisition`/`StoreRequisition`'s own
 * established pattern in this module.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $po_number
 * @property int $supplier_id
 * @property int|null $requisition_id
 * @property int|null $quotation_id
 * @property int $cost_centre_id
 * @property int|null $budget_line_id
 * @property Carbon $order_date
 * @property Carbon|null $expected_delivery
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property string $currency
 * @property int|null $exchange_rate_id
 * @property int $base_total_minor
 * @property int $committed_minor
 * @property int $released_minor
 * @property string $status
 * @property int|null $approved_by
 * @property int|null $created_by
 */
class PurchaseOrder extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'po_number', 'supplier_id', 'requisition_id',
        'quotation_id', 'cost_centre_id', 'budget_line_id', 'order_date', 'expected_delivery',
        'delivery_address', 'subtotal_minor', 'tax_minor', 'total_minor', 'currency', 'exchange_rate_id',
        'base_total_minor', 'committed_minor', 'released_minor', 'status', 'approval_request_id',
        'approved_by', 'sent_at', 'document_id', 'terms_and_conditions', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            PeriodGuard::assertWritable($model);
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('status') && $model->status === 'approved') {
                PeriodGuard::assertWritable($model);
            }
        });
    }

    public function throwIfNotApprovable(): void
    {
        if ($this->status !== 'pending_approval') {
            throw new InvalidStateTransitionException(
                "Purchase order #{$this->id} must be pending approval (currently {$this->status}).",
                ['purchase_order_id' => $this->id, 'status' => $this->status],
            );
        }
    }

    public function isFullyReceived(): bool
    {
        return $this->lines->every(fn (PurchaseOrderLine $l): bool => (float) $l->quantity_received + (float) $l->quantity_rejected >= (float) $l->quantity_ordered);
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PurchaseOrderFactory::new();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<PurchaseRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }
}
