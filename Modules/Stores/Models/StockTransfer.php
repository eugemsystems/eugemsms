<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\Journal;
use Modules\Stores\Database\Factories\StockTransferFactory;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-018/019.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $transfer_number
 * @property int $from_store_id
 * @property int $to_store_id
 * @property string $reason
 * @property string $status
 * @property int|null $dispatched_by
 * @property Carbon|null $dispatched_at
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property string|null $discrepancy_note
 * @property int|null $journal_id
 */
class StockTransfer extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StockTransferFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'transfer_number', 'from_store_id', 'to_store_id', 'reason', 'status',
        'dispatched_by', 'dispatched_at', 'received_by', 'received_at', 'discrepancy_note', 'journal_id',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StockTransferFactory::new();
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
     * @return BelongsTo<User, $this>
     */
    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return HasMany<StockTransferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class, 'transfer_id');
    }
}
