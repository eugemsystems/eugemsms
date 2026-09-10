<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\StoreRequisitionLineFactory;

/**
 * Book H1 FIN-09 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $requisition_id
 * @property int $item_id
 * @property float $quantity_requested
 * @property float|null $quantity_approved
 * @property float|null $quantity_issued
 * @property float|null $quantity_returned
 * @property string $unit
 * @property int|null $unit_cost_minor
 * @property int|null $line_cost_minor
 * @property int|null $substituted_item_id
 * @property string|null $substitution_note
 * @property string|null $notes
 */
class StoreRequisitionLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StoreRequisitionLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'requisition_id', 'item_id', 'quantity_requested', 'quantity_approved',
        'quantity_issued', 'quantity_returned', 'unit', 'unit_cost_minor', 'line_cost_minor',
        'substituted_item_id', 'substitution_note', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'decimal:4',
            'quantity_approved' => 'decimal:4',
            'quantity_issued' => 'decimal:4',
            'quantity_returned' => 'decimal:4',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StoreRequisitionLineFactory::new();
    }

    /**
     * @return BelongsTo<StoreRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StoreRequisition::class, 'requisition_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function substitutedItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'substituted_item_id');
    }
}
