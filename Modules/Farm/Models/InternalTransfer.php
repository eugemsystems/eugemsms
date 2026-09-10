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
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Farm\Database\Factories\InternalTransferFactory;
use Modules\Finance\Models\Journal;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * Book H2 OPS-03 §2/§3 ⭐⭐/BR-OPS-03-008/009 — closes the Book F
 * interface.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $transfer_number
 * @property int $production_unit_id
 * @property int $from_store_id
 * @property int $to_store_id
 * @property Carbon $transfer_date
 * @property int|null $harvest_id
 * @property int|null $output_id
 * @property int $item_id
 * @property float $quantity
 * @property string $unit
 * @property int $unit_cost_minor
 * @property int $total_cost_minor
 * @property string $currency
 * @property int|null $market_price_minor
 * @property int|null $journal_id
 * @property int $dispatched_by
 * @property int|null $received_by
 * @property string $status
 */
class InternalTransfer extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InternalTransferFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'transfer_number', 'production_unit_id', 'from_store_id', 'to_store_id',
        'transfer_date', 'harvest_id', 'output_id', 'item_id', 'quantity', 'unit', 'unit_cost_minor',
        'total_cost_minor', 'currency', 'market_price_minor', 'journal_id', 'dispatched_by', 'received_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'quantity' => 'decimal:3',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return InternalTransferFactory::new();
    }

    /**
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    /**
     * @return BelongsTo<Harvest, $this>
     */
    public function harvest(): BelongsTo
    {
        return $this->belongsTo(Harvest::class);
    }

    /**
     * @return BelongsTo<ProductionOutput, $this>
     */
    public function output(): BelongsTo
    {
        return $this->belongsTo(ProductionOutput::class, 'output_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
