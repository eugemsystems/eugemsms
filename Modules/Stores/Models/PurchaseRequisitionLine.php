<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\PurchaseRequisitionLineFactory;

/**
 * @property int $id
 * @property int $school_id
 * @property int $requisition_id
 * @property int|null $item_id
 * @property string $description
 * @property float $quantity
 * @property string $unit
 * @property int|null $estimated_unit_minor
 * @property int|null $estimated_total_minor
 * @property string $currency
 * @property float $ordered_quantity
 */
class PurchaseRequisitionLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PurchaseRequisitionLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'requisition_id', 'item_id', 'description', 'specification', 'quantity', 'unit',
        'estimated_unit_minor', 'estimated_total_minor', 'currency', 'ordered_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'ordered_quantity' => 'decimal:4',
        ];
    }

    public function isService(): bool
    {
        return $this->item_id === null;
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PurchaseRequisitionLineFactory::new();
    }

    /**
     * @return BelongsTo<PurchaseRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
