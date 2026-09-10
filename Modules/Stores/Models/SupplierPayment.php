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
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\Journal;
use Modules\Stores\Database\Factories\SupplierPaymentFactory;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-019/020/021.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $payment_number
 * @property int $supplier_id
 * @property Carbon $payment_date
 * @property string $payment_method
 * @property int $gross_minor
 * @property int $withholding_minor
 * @property int $net_minor
 * @property string $currency
 * @property string $status
 * @property int|null $approved_by
 * @property int|null $journal_id
 * @property int|null $batch_id
 */
class SupplierPayment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SupplierPaymentFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'term_id', 'payment_number', 'supplier_id', 'payment_date', 'payment_method',
        'bank_account_id', 'reference', 'gross_minor', 'withholding_minor', 'net_minor', 'currency',
        'exchange_rate_id', 'status', 'approval_request_id', 'approved_by', 'journal_id',
        'remittance_document_id', 'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            PeriodGuard::assertWritable($model);
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SupplierPaymentFactory::new();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return HasMany<SupplierPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'payment_id');
    }
}
