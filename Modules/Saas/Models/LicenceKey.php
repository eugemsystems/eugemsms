<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Database\Factories\LicenceKeyFactory;

/**
 * Book J SAA-01 §2/BR-SAA-01-007 — on-premise deployments.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $subscription_id
 * @property string $key_value
 * @property string|null $installation_uuid
 * @property Carbon|null $last_validated_at
 * @property int $offline_grace_days
 * @property string $status
 */
class LicenceKey extends Model
{
    /** @use HasFactory<LicenceKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'subscription_id', 'key_value', 'installation_uuid', 'last_validated_at',
        'offline_grace_days', 'status',
    ];

    protected function casts(): array
    {
        return [
            'last_validated_at' => 'datetime',
            'offline_grace_days' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LicenceKeyFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isWithinOfflineGrace(): bool
    {
        if ($this->last_validated_at === null) {
            return false;
        }

        return $this->last_validated_at->copy()->addDays($this->offline_grace_days)->isFuture();
    }
}
