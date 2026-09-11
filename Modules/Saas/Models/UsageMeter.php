<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Tenant;
use Modules\Saas\Database\Factories\UsageMeterFactory;

/**
 * Book J SAA-01 §2/BR-SAA-01-003/004.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $subscription_id
 * @property string $period_month
 * @property string $metric
 * @property float $usage_value
 * @property float|null $limit_value
 * @property bool $soft_warning_sent
 * @property bool $hard_limit_reached
 */
class UsageMeter extends Model
{
    /** @use HasFactory<UsageMeterFactory> */
    use HasFactory;

    public const string METRIC_ACTIVE_LEARNERS = 'active_learners';

    public const string METRIC_STORAGE_GB = 'storage_gb';

    public const string METRIC_MESSAGES_SENT = 'messages_sent';

    public const string METRIC_API_CALLS = 'api_calls';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'period_month', 'metric', 'usage_value', 'limit_value',
        'soft_warning_sent', 'hard_limit_reached',
    ];

    protected function casts(): array
    {
        return [
            'usage_value' => 'float',
            'limit_value' => 'float',
            'soft_warning_sent' => 'boolean',
            'hard_limit_reached' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return UsageMeterFactory::new();
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
}
