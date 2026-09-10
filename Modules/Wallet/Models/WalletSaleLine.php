<?php

declare(strict_types=1);

namespace Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Models\StockMovement;
use Modules\Wallet\Database\Factories\WalletSaleLineFactory;

/**
 * Book H3 FIN-14 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $sale_id
 * @property int $product_id
 * @property float $quantity
 * @property int $unit_price_minor
 * @property int $line_total_minor
 * @property string $tax_type
 * @property int $tax_minor
 * @property int|null $stock_movement_id
 */
class WalletSaleLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WalletSaleLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'sale_id', 'product_id', 'quantity', 'unit_price_minor', 'line_total_minor',
        'tax_type', 'tax_minor', 'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WalletSaleLineFactory::new();
    }

    /**
     * @return BelongsTo<WalletSale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(WalletSale::class);
    }

    /**
     * @return BelongsTo<WalletProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(WalletProduct::class);
    }

    /**
     * @return BelongsTo<StockMovement, $this>
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
