<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Finance\Database\Factories\ReceiptFactory;
use Modules\People\Models\Student;

/**
 * Book B FIN-04 §2/BR-FIN-04-008/010 ⭐. Money is receipted the moment
 * it arrives — an unidentified deposit still becomes a `Receipt`
 * (`is_suspense = true`), never held off-ledger. Only `status` and the
 * allocation cache columns ever change after creation.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $receipt_number
 * @property int|null $till_session_id
 * @property string $receipt_type
 * @property string $payer_type
 * @property int|null $payer_id
 * @property string $payer_name
 * @property string|null $payer_phone
 * @property int|null $student_id
 * @property int $amount_minor
 * @property string $currency
 * @property int $base_amount_minor
 * @property int|null $exchange_rate_id
 * @property int $allocated_minor
 * @property int $unallocated_minor
 * @property bool $is_suspense
 * @property Carbon $received_at
 * @property Carbon $effective_date
 * @property string|null $narration
 * @property string $status
 * @property int|null $journal_id
 * @property string|null $fiscalisation_status
 * @property int|null $fiscal_receipt_id Book H3 FIN-13 §3 — additive, nullable; see that migration's docblock.
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $void_journal_id
 * @property int $received_by
 * @property-read Collection<int, ReceiptTender> $tenders
 * @property-read Collection<int, ReceiptAllocation> $allocations
 */
class Receipt extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = [
        'status', 'allocated_minor', 'unallocated_minor', 'voided_at', 'void_reason',
        'void_journal_id', 'fiscalisation_status', 'journal_id', 'fiscal_receipt_id',
    ];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'receipt_number', 'till_session_id',
        'receipt_type', 'payer_type', 'payer_id', 'payer_name', 'payer_phone', 'student_id',
        'amount_minor', 'currency', 'base_amount_minor', 'exchange_rate_id', 'allocated_minor',
        'unallocated_minor', 'is_suspense', 'received_at', 'effective_date', 'narration',
        'status', 'journal_id', 'document_id', 'fiscalisation_status', 'voided_at',
        'void_reason', 'void_journal_id', 'received_by', 'created_at', 'fiscal_receipt_id',
    ];

    protected function casts(): array
    {
        return [
            'is_suspense' => 'boolean',
            'received_at' => 'datetime',
            'effective_date' => 'date',
            'voided_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReceiptFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Model $model): void {
            PeriodGuard::assertWritable($model);
        });

        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A receipt may only change status and its allocation/void cache columns after creation (BR-FIN-04-008).',
                    ['dirty' => $illegal],
                );
            }
        });
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<TillSession, $this>
     */
    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(TillSession::class);
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
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return HasMany<ReceiptTender, $this>
     */
    public function tenders(): HasMany
    {
        return $this->hasMany(ReceiptTender::class);
    }

    /**
     * @return HasMany<ReceiptAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }
}
