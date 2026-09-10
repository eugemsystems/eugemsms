<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book G BRD-08 §2/§3 ⭐⭐/BR-BRD-08-005/006 — a stream separate from
 * `CORE-08`'s general audit, hash-chained, append-only. See
 * `Modules\Core\Models\FinancialAuditLogEntry` for why this guard is a
 * model-level guard rather than a DB grant REVOKE in this pass.
 *
 * @property int $id
 * @property int $school_id
 * @property int $sequence
 * @property string $event_type
 * @property int|null $case_id
 * @property int|null $concern_id
 * @property int $user_id
 * @property string $user_role_at_time
 * @property string|null $access_basis
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $payload_hash
 * @property string|null $previous_hash
 * @property Carbon $occurred_at
 */
class SafeguardingAuditEntry extends Model
{
    use BelongsToSchool;

    protected $table = 'safeguarding_audit';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'sequence', 'event_type', 'case_id', 'concern_id', 'user_id', 'user_role_at_time',
        'access_basis', 'ip_address', 'user_agent', 'payload_hash', 'previous_hash', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('safeguarding_audit is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('safeguarding_audit is append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
