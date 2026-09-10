<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

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
use Modules\Finance\Database\Factories\InvoiceFactory;
use Modules\People\Models\Student;

/**
 * Book B FIN-03 §2/BR-FIN-03-004 ⭐. An issued invoice is never edited
 * — only its status and the four cache columns (`paid_minor`,
 * `credited_minor`, `written_off_minor`, `balance_minor`, plus the
 * void trail) ever change, mirroring `LearnerFeeAssignment`'s guard.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $invoice_number
 * @property string $invoice_type
 * @property int $student_id
 * @property string $billed_party_type
 * @property int $billed_party_id
 * @property string $liability_percent
 * @property int|null $assignment_id
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property int $gross_minor
 * @property int $discount_minor
 * @property int $net_minor
 * @property int $paid_minor
 * @property int $credited_minor
 * @property int $written_off_minor
 * @property int $balance_minor
 * @property string $currency
 * @property string $status
 * @property int|null $journal_id
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $replaced_by_invoice_id
 * @property-read Collection<int, InvoiceLine> $lines
 */
class Invoice extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use HasUlid;

    private const array MUTABLE_AFTER_CREATE = [
        'status', 'paid_minor', 'credited_minor', 'written_off_minor', 'balance_minor',
        'voided_at', 'void_reason', 'replaced_by_invoice_id', 'journal_id',
    ];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'invoice_number', 'invoice_type',
        'student_id', 'billed_party_type', 'billed_party_id', 'liability_percent',
        'assignment_id', 'issue_date', 'due_date', 'gross_minor', 'discount_minor',
        'net_minor', 'paid_minor', 'credited_minor', 'written_off_minor', 'balance_minor',
        'currency', 'status', 'journal_id', 'document_id', 'voided_at', 'void_reason',
        'replaced_by_invoice_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'liability_percent' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return InvoiceFactory::new();
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
                    'An issued invoice is never edited (BR-FIN-03-004) — only status, the cache columns, and the void trail may change.',
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
     * @return BelongsTo<LearnerFeeAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LearnerFeeAssignment::class, 'assignment_id');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_invoice_id');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
