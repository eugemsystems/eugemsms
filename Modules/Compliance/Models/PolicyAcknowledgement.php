<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\PolicyAcknowledgementFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book H3 CMP-04 §2/BR-CMP-04-001 — APPEND-ONLY. `policy_version` is
 * frozen at acknowledgement time; a superseding version never
 * retroactively touches an earlier acknowledgement (AC-CMP-04-001).
 *
 * @property int $id
 * @property int $school_id
 * @property int $policy_id
 * @property string $policy_version
 * @property string $acknowledged_by_type
 * @property int $acknowledged_by_id
 * @property Carbon $acknowledged_at
 * @property string|null $ip_address
 * @property string $method
 */
class PolicyAcknowledgement extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PolicyAcknowledgementFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'policy_id', 'policy_version', 'acknowledged_by_type', 'acknowledged_by_id',
        'acknowledged_at', 'ip_address', 'method',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PolicyAcknowledgementFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('policy_acknowledgements is append-only — an acknowledgement is never edited.');
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('policy_acknowledgements is append-only — an acknowledgement is never deleted.');
        });
    }

    /**
     * @return BelongsTo<Policy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }
}
