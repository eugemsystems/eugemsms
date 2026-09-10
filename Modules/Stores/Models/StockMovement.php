<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\Journal;
use Modules\Stores\Database\Factories\StockMovementFactory;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-001 ⭐ — APPEND-ONLY, the source of
 * truth. See `Modules\Core\Models\FinancialAuditLogEntry` for why the
 * real DB-grant REVOKE is a deployment step, not a migration; this
 * model-level guard is what's actually tested here.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $store_id
 * @property int $item_id
 * @property int|null $lot_id
 * @property string $movement_type
 * @property string $direction
 * @property float $quantity
 * @property int $unit_cost_minor
 * @property int $total_cost_minor
 * @property string $currency
 * @property int $base_total_minor
 * @property float $balance_after
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $cost_centre_id
 * @property int|null $expense_account_id
 * @property int|null $journal_id
 * @property string|null $reference
 * @property string|null $notes
 * @property int $performed_by
 * @property Carbon $occurred_at
 */
class StockMovement extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'store_id', 'item_id', 'lot_id', 'movement_type',
        'direction', 'quantity', 'unit_cost_minor', 'total_cost_minor', 'currency', 'base_total_minor',
        'balance_after', 'source_type', 'source_id', 'cost_centre_id', 'expense_account_id', 'journal_id',
        'reference', 'notes', 'performed_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('stock_movements is append-only and can never be updated (BR-FIN-09-001).');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('stock_movements is append-only and can never be deleted (BR-FIN-09-001).');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StockMovementFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
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

    /**
     * @return BelongsTo<StockLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
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
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
