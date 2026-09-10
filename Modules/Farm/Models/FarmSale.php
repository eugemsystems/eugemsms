<?php

declare(strict_types=1);

namespace Modules\Farm\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Farm\Database\Factories\FarmSaleFactory;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\Receipt;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-016.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $sale_number
 * @property int $production_unit_id
 * @property Carbon $sale_date
 * @property string $buyer_name
 * @property string|null $buyer_contact
 * @property string $item_description
 * @property float $quantity
 * @property string $unit
 * @property int $unit_price_minor
 * @property int $total_minor
 * @property string $currency
 * @property int|null $cost_of_sales_minor
 * @property int|null $receipt_id
 * @property int|null $fiscal_receipt_id
 * @property int|null $journal_id
 */
class FarmSale extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FarmSaleFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'sale_number', 'production_unit_id', 'sale_date', 'buyer_name',
        'buyer_contact', 'item_description', 'quantity', 'unit', 'unit_price_minor', 'total_minor',
        'currency', 'cost_of_sales_minor', 'receipt_id', 'fiscal_receipt_id', 'journal_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'quantity' => 'decimal:3',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FarmSaleFactory::new();
    }

    /**
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
