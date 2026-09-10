<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\StoreItemSettingFactory;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-024 — reorder breaches suggest a
 * purchase requisition in `FIN-08`, never an automatic order.
 *
 * @property int $id
 * @property int $school_id
 * @property int $store_id
 * @property int $item_id
 * @property float|null $reorder_level
 * @property float|null $reorder_quantity
 * @property float|null $maximum_level
 * @property string|null $bin_location
 * @property bool $is_stocked
 */
class StoreItemSetting extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StoreItemSettingFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'store_id', 'item_id', 'reorder_level', 'reorder_quantity',
        'maximum_level', 'bin_location', 'is_stocked',
    ];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'maximum_level' => 'decimal:4',
            'is_stocked' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StoreItemSettingFactory::new();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
