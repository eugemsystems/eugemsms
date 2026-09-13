<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

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
use Modules\Finance\Database\Factories\FeeWaiverFactory;
use Modules\People\Models\Student;

/**
 * Book B FIN-03 §2/BR-FIN-03-012/013. `type` is `waiver` (reduces the
 * amount owed before it is chased) or `write_off` (recognises an
 * amount as uncollectable after it has been chased) — they post to
 * different accounts and are never conflated.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $student_id
 * @property int|null $invoice_id
 * @property string $type
 * @property int $amount_minor
 * @property string $currency
 * @property string $reason_code
 * @property string $reason
 * @property int|null $supporting_document_id
 * @property string $status
 * @property int|null $approval_request_id
 * @property int|null $journal_id
 * @property int $requested_by
 * @property int|null $approved_by
 * @property Carbon $created_at
 */
class FeeWaiver extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeWaiverFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = ['status', 'journal_id', 'approved_by', 'approval_request_id'];

    protected $fillable = [
        'school_id', 'term_id', 'student_id', 'invoice_id', 'type', 'amount_minor',
        'currency', 'reason_code', 'reason', 'supporting_document_id', 'status',
        'approval_request_id', 'journal_id', 'requested_by', 'approved_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeeWaiverFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A fee_waivers row may only change status, journal_id, approved_by, or approval_request_id after creation.',
                    ['dirty' => $illegal],
                );
            }
        });
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
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
