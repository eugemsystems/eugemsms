<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Tenant;
use Modules\Saas\Database\Factories\SubscriptionFactory;

/**
 * Book J SAA-01 §2 ⭐ — the vendor's contract with a `Tenant`. `status`
 * drives `EnsureSubscriptionActive` (§3): trial|active|past_due|grace|
 * suspended|cancelled.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $plan_id
 * @property array<int, int> $covered_school_ids
 * @property string $billing_currency
 * @property int|null $learner_count_at_billing
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon $current_period_start
 * @property Carbon $current_period_end
 * @property Carbon|null $grace_period_ends_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property bool $auto_renew
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    use HasUlid;

    public const array ACTIVE_STATUSES = ['trial', 'active'];

    public const array DEGRADED_STATUSES = ['past_due', 'grace', 'suspended', 'cancelled'];

    protected $fillable = [
        'tenant_id', 'plan_id', 'covered_school_ids', 'billing_currency', 'learner_count_at_billing',
        'status', 'trial_ends_at', 'current_period_start', 'current_period_end', 'grace_period_ends_at',
        'cancelled_at', 'cancellation_reason', 'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'covered_school_ids' => 'array',
            'learner_count_at_billing' => 'integer',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'grace_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubscriptionFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<SubscriptionChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(SubscriptionChange::class);
    }

    /**
     * @return HasMany<TenantInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(TenantInvoice::class);
    }

    public function isFullyActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
