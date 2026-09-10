<?php

declare(strict_types=1);

namespace Modules\Farm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Farm\Database\Factories\CropInputFactory;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StoreRequisition;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-002 — real `FIN-09` linkage.
 *
 * @property int $id
 * @property int $school_id
 * @property int $crop_cycle_id
 * @property string $input_type
 * @property int|null $item_id
 * @property string $description
 * @property float $quantity
 * @property string $unit
 * @property Carbon $applied_on
 * @property int|null $store_requisition_id
 * @property int $cost_minor
 * @property string $currency
 * @property int|null $applied_by
 */
class CropInput extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CropInputFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'crop_cycle_id', 'input_type', 'item_id', 'description', 'quantity', 'unit',
        'applied_on', 'store_requisition_id', 'cost_minor', 'currency', 'applied_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'applied_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CropInputFactory::new();
    }

    /**
     * @return BelongsTo<CropCycle, $this>
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
