<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Database\Factories\ReceiptTenderFactory;

/**
 * Book B FIN-04 §2/BR-FIN-04-018. `is_cleared`/`cleared_at` are the
 * only columns `ClearChequeAction` may ever touch.
 *
 * @property int $id
 * @property int $school_id
 * @property int $receipt_id
 * @property string $tender_type
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $reference
 * @property int|null $bank_account_id
 * @property Carbon|null $cleared_at
 * @property bool $is_cleared
 */
class ReceiptTender extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReceiptTenderFactory> */
    use HasFactory;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = ['is_cleared', 'cleared_at'];

    protected $fillable = [
        'school_id', 'receipt_id', 'tender_type', 'amount_minor', 'currency', 'reference',
        'bank_account_id', 'cleared_at', 'is_cleared',
    ];

    protected function casts(): array
    {
        return [
            'cleared_at' => 'datetime',
            'is_cleared' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReceiptTenderFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A receipt_tenders row may only change is_cleared/cleared_at after creation.',
                    ['dirty' => $illegal],
                );
            }
        });
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }
}
