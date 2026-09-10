<?php

declare(strict_types=1);

namespace Modules\Wallet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\Fiscal\Models\FiscalReceipt;
use Modules\People\Models\Student;
use Modules\Wallet\Database\Factories\WalletSaleFactory;

/**
 * Book H3 FIN-14 §2/§4/BR-FIN-14-010/011.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $spend_point_id
 * @property string $sale_number
 * @property int|null $student_id
 * @property int|null $wallet_id
 * @property Carbon $sold_at
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property string $currency
 * @property string $payment_method
 * @property string|null $identification_method
 * @property int|null $cost_of_sales_minor
 * @property int $operator_id
 * @property int|null $till_session_id
 * @property int|null $journal_id
 * @property int|null $fiscal_receipt_id
 * @property string $device_source
 * @property string|null $offline_reference
 * @property string $status
 */
class WalletSale extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WalletSaleFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'spend_point_id', 'sale_number', 'student_id', 'wallet_id', 'sold_at',
        'subtotal_minor', 'tax_minor', 'total_minor', 'currency', 'payment_method', 'identification_method',
        'cost_of_sales_minor', 'operator_id', 'till_session_id', 'journal_id', 'fiscal_receipt_id',
        'device_source', 'offline_reference', 'status',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WalletSaleFactory::new();
    }

    /**
     * @return BelongsTo<SpendPoint, $this>
     */
    public function spendPoint(): BelongsTo
    {
        return $this->belongsTo(SpendPoint::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<StudentWallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StudentWallet::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * @return BelongsTo<FiscalReceipt, $this>
     */
    public function fiscalReceipt(): BelongsTo
    {
        return $this->belongsTo(FiscalReceipt::class);
    }

    /**
     * @return HasMany<WalletSaleLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(WalletSaleLine::class, 'sale_id');
    }
}
