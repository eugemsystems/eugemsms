<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\PeriodRolloverFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Support\RolloverStatus;

/**
 * Book A CORE-03 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $from_term_id
 * @property int $to_term_id
 * @property RolloverStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int $initiated_by
 * @property int|null $approved_by
 * @property array<int, array<string, mixed>>|null $validation_report
 * @property array<int, array<string, mixed>>|null $step_log
 * @property array<string, mixed>|null $exception_report
 * @property int|null $report_document_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Term $fromTerm
 * @property-read Term $toTerm
 * @property-read User $initiator
 * @property-read User|null $approver
 */
class PeriodRollover extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PeriodRolloverFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id',
        'from_term_id',
        'to_term_id',
        'status',
        'started_at',
        'completed_at',
        'initiated_by',
        'approved_by',
        'validation_report',
        'step_log',
        'exception_report',
        'report_document_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RolloverStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'validation_report' => 'array',
            'step_log' => 'array',
            'exception_report' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PeriodRolloverFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function fromTerm(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'from_term_id');
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function toTerm(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'to_term_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
