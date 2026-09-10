<?php

declare(strict_types=1);

namespace Modules\Utilities\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\Journal;
use Modules\Utilities\Database\Factories\PrepaidTokenPurchaseFactory;

/**
 * Book H2 OPS-04 §2/§3 🇿🇼 ⭐/BR-OPS-04-001/002/003/004.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $meter_id
 * @property Carbon $purchased_at
 * @property string $token_number
 * @property int $amount_paid_minor
 * @property string $currency
 * @property float $units_purchased
 * @property int $levies_minor
 * @property int|null $effective_rate_minor
 * @property string|null $vendor
 * @property int $purchased_by
 * @property Carbon|null $credited_at
 * @property int|null $credited_by
 * @property bool $credit_confirmed
 * @property string $status
 * @property string|null $failure_note
 * @property int|null $journal_id
 */
class PrepaidTokenPurchase extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PrepaidTokenPurchaseFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'meter_id', 'purchased_at', 'token_number', 'amount_paid_minor', 'currency',
        'units_purchased', 'levies_minor', 'effective_rate_minor', 'vendor', 'receipt_file_id', 'purchased_by',
        'credited_at', 'credited_by', 'credit_confirmed', 'status', 'failure_note', 'journal_id',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'units_purchased' => 'decimal:3',
            'credited_at' => 'datetime',
            'credit_confirmed' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PrepaidTokenPurchaseFactory::new();
    }

    /**
     * @return BelongsTo<Meter, $this>
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
