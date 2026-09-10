<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Operations\Database\Factories\WorkOrderPartFactory;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StoreRequisition;

/**
 * Book H2 OPS-02 §2/§3 ⭐/BR-OPS-02-005 — the real `FIN-09` linkage.
 *
 * @property int $id
 * @property int $school_id
 * @property int $work_order_id
 * @property int|null $item_id
 * @property string $description
 * @property float $quantity
 * @property string $unit
 * @property string $source
 * @property int|null $store_requisition_id
 * @property int|null $purchase_order_id
 * @property int|null $unit_cost_minor
 * @property int|null $line_cost_minor
 */
class WorkOrderPart extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WorkOrderPartFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'work_order_id', 'item_id', 'description', 'quantity', 'unit', 'source',
        'store_requisition_id', 'purchase_order_id', 'unit_cost_minor', 'line_cost_minor', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WorkOrderPartFactory::new();
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<StoreRequisition, $this>
     */
    public function storeRequisition(): BelongsTo
    {
        return $this->belongsTo(StoreRequisition::class);
    }
}
