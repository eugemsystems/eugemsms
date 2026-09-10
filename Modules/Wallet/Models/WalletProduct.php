<?php

declare(strict_types=1);

namespace Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Models\InventoryItem;
use Modules\Wallet\Database\Factories\WalletProductFactory;

/**
 * Book H3 FIN-14 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $spend_point_id
 * @property int|null $item_id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property int $price_minor
 * @property string $currency
 * @property string $tax_type
 * @property string|null $barcode
 * @property int|null $image_file_id
 * @property bool $is_active
 * @property int|null $sort_order
 */
class WalletProduct extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WalletProductFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'spend_point_id', 'item_id', 'code', 'name', 'category', 'price_minor', 'currency',
        'tax_type', 'barcode', 'image_file_id', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WalletProductFactory::new();
    }

    /**
     * @return BelongsTo<SpendPoint, $this>
     */
    public function spendPoint(): BelongsTo
    {
        return $this->belongsTo(SpendPoint::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
