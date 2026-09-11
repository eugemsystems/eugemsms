<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Saas\Database\Factories\SubscriptionChangeFactory;

/**
 * Book J SAA-01 §2 — APPEND-ONLY. See
 * `Modules\Welfare\Models\SafeguardingAuditEntry` for why this is a
 * model-level guard rather than a DB grant REVOKE in this pass.
 *
 * @property int $id
 * @property int $subscription_id
 * @property string $change_type
 * @property int|null $from_plan_id
 * @property int|null $to_plan_id
 * @property int|null $proration_credit_minor
 * @property Carbon $effective_from
 * @property string|null $reason
 * @property int|null $performed_by
 * @property Carbon $occurred_at
 */
class SubscriptionChange extends Model
{
    /** @use HasFactory<SubscriptionChangeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'subscription_id', 'change_type', 'from_plan_id', 'to_plan_id', 'proration_credit_minor',
        'effective_from', 'reason', 'performed_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'proration_credit_minor' => 'integer',
            'effective_from' => 'date',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubscriptionChangeFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('subscription_changes is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('subscription_changes is append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'from_plan_id');
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'to_plan_id');
    }
}
