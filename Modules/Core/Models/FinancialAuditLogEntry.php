<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book A CORE-08 §2/BR-CORE-08-005. Append-only. The spec's real
 * enforcement — revoking UPDATE/DELETE for the application database
 * user — is an environment-specific operational task (the DB user
 * name and grant permissions differ per deployment, and running a
 * REVOKE from a migration in this shared dev database risks locking
 * later migrations out of the table entirely) — tracked separately as
 * a deployment step, not run from here. This model-level guard is the
 * layer that's actually testable and always in effect regardless of
 * environment.
 *
 * @property int $id
 * @property int $school_id
 * @property int $sequence
 * @property string $event_type
 * @property string $subject_type
 * @property int $subject_id
 * @property int|null $amount_minor
 * @property string|null $amount_currency
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property string|null $previous_hash
 * @property int $causer_id
 * @property int|null $impersonator_id
 * @property int $academic_year_id
 * @property int|null $term_id
 * @property string|null $ip_address
 * @property Carbon $occurred_at
 */
class FinancialAuditLogEntry extends Model
{
    protected $table = 'financial_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'sequence', 'event_type', 'subject_type', 'subject_id',
        'amount_minor', 'amount_currency', 'payload', 'payload_hash', 'previous_hash',
        'causer_id', 'impersonator_id', 'academic_year_id', 'term_id', 'ip_address',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('financial_audit_log is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('financial_audit_log is append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
