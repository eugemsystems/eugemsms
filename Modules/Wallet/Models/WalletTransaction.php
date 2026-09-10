<?php

declare(strict_types=1);

namespace Modules\Wallet\Models;

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
use Modules\Wallet\Database\Factories\WalletTransactionFactory;

/**
 * Book H3 FIN-14 §2/BR-FIN-14-002 — append-only.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $wallet_id
 * @property string $transaction_type
 * @property string $direction
 * @property int $amount_minor
 * @property int $balance_after_minor
 * @property string $currency
 * @property int|null $spend_point_id
 * @property int|null $sale_id
 * @property int|null $receipt_id
 * @property int|null $journal_id
 * @property string|null $reference
 * @property int|null $performed_by
 * @property Carbon $occurred_at
 */
class WalletTransaction extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'wallet_id', 'transaction_type', 'direction', 'amount_minor',
        'balance_after_minor', 'currency', 'spend_point_id', 'sale_id', 'receipt_id', 'journal_id',
        'reference', 'performed_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WalletTransactionFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('wallet_transactions is append-only — a movement is never edited.');
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('wallet_transactions is append-only — a movement is never deleted.');
        });
    }

    /**
     * @return BelongsTo<StudentWallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StudentWallet::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
