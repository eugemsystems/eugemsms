<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Saas\Database\Factories\SubscriptionPlanFactory;

/**
 * Book J SAA-01 §2. The vendor's plan catalogue — not tenant data.
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property string $name
 * @property string $tier
 * @property int|null $price_per_learner_minor
 * @property int|null $flat_monthly_minor
 * @property string $currency
 * @property array<int, string> $included_modules
 * @property int|null $learner_band_min
 * @property int|null $learner_band_max
 * @property int|null $seat_limit_admin
 * @property int|null $storage_quota_gb
 * @property int|null $message_quota_monthly
 * @property bool $is_active
 */
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'code', 'name', 'tier', 'price_per_learner_minor', 'flat_monthly_minor', 'currency',
        'included_modules', 'learner_band_min', 'learner_band_max', 'seat_limit_admin',
        'storage_quota_gb', 'message_quota_monthly', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_per_learner_minor' => 'integer',
            'flat_monthly_minor' => 'integer',
            'included_modules' => 'array',
            'learner_band_min' => 'integer',
            'learner_band_max' => 'integer',
            'seat_limit_admin' => 'integer',
            'storage_quota_gb' => 'integer',
            'message_quota_monthly' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubscriptionPlanFactory::new();
    }

    /**
     * The monthly-equivalent price used to compare plans for
     * upgrade/downgrade direction (BR-SAA-01-006) and to prorate a
     * mid-period switch — a flat-fee plan ignores `$learnerCount`.
     */
    public function monthlyPriceMinor(int $learnerCount): int
    {
        if ($this->flat_monthly_minor !== null) {
            return $this->flat_monthly_minor;
        }

        return ($this->price_per_learner_minor ?? 0) * $learnerCount;
    }

    public function limitFor(string $metric): ?float
    {
        return match ($metric) {
            'active_learners' => $this->learner_band_max !== null ? (float) $this->learner_band_max : null,
            'storage_gb' => $this->storage_quota_gb !== null ? (float) $this->storage_quota_gb : null,
            'messages_sent' => $this->message_quota_monthly !== null ? (float) $this->message_quota_monthly : null,
            default => null,
        };
    }
}
