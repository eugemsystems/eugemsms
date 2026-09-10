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
use Modules\Finance\Database\Factories\LearnerFeeAssignmentFactory;
use Modules\People\Models\Student;

/**
 * Book B FIN-02 §2 ⭐/BR-FIN-02-003. The computed result of one billing
 * pass for one learner in one term. `resolution_trace` is immutable
 * once written — it is the historical record of why this exact amount
 * was billed (AC-FIN-02-009/010) — only `status` and `invoice_id` (once
 * `FIN-03` exists to populate it) ever change after creation.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $structure_id
 * @property int $structure_version
 * @property array<string, mixed> $resolution_trace
 * @property Carbon $computed_at
 * @property int $computed_by
 * @property string $status
 * @property int|null $invoice_id
 * @property int|null $billing_run_id
 * @property-read Collection<int, LearnerFeeLine> $lines
 */
class LearnerFeeAssignment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LearnerFeeAssignmentFactory> */
    use HasFactory;

    use HasUlid;

    private const array MUTABLE_AFTER_CREATE = ['status', 'invoice_id'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'structure_id',
        'structure_version', 'resolution_trace', 'computed_at', 'computed_by',
        'status', 'invoice_id', 'billing_run_id',
    ];

    protected function casts(): array
    {
        return [
            'structure_version' => 'integer',
            'resolution_trace' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LearnerFeeAssignmentFactory::new();
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
                    'A learner_fee_assignments row may only change status or invoice_id after creation — the resolution_trace and computed lines are the historical record (AC-FIN-02-009).',
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
     * @return BelongsTo<FeeStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'structure_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    /**
     * @return HasMany<LearnerFeeLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(LearnerFeeLine::class, 'assignment_id');
    }

    /**
     * @return BelongsTo<BillingRun, $this>
     */
    public function billingRun(): BelongsTo
    {
        return $this->belongsTo(BillingRun::class);
    }
}
