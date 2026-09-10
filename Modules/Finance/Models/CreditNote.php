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
use Modules\Finance\Database\Factories\CreditNoteFactory;
use Modules\People\Models\Student;

/**
 * Book B FIN-03 §2/BR-FIN-03-011. Never a receipt — posts
 * `Dr Fee Income / Cr Fee Debtors`, the reverse of a fee billing
 * entry.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $credit_note_number
 * @property int $student_id
 * @property int|null $invoice_id
 * @property string $reason_code
 * @property string $reason
 * @property int $amount_minor
 * @property int $applied_minor
 * @property string $currency
 * @property Carbon $issue_date
 * @property string $status
 * @property int|null $journal_id
 * @property int|null $approval_request_id
 * @property int $raised_by
 * @property int|null $approved_by
 * @property-read Collection<int, CreditNoteLine> $lines
 */
class CreditNote extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CreditNoteFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = ['status', 'applied_minor', 'journal_id', 'approved_by'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'credit_note_number', 'student_id',
        'invoice_id', 'reason_code', 'reason', 'amount_minor', 'applied_minor', 'currency',
        'issue_date', 'status', 'journal_id', 'document_id', 'approval_request_id',
        'raised_by', 'approved_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CreditNoteFactory::new();
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
                    'A credit note may only change status, applied_minor, journal_id, or approved_by after creation.',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    /**
     * @return HasMany<CreditNoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class);
    }
}
