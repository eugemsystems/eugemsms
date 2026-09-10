<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Comms\Database\Factories\AutomationRuleFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book I COM-02 §2/BR-COM-02-008 ⭐.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $name
 * @property string $notification_key
 * @property string $trigger_type
 * @property string|null $event_name
 * @property string|null $schedule_cron
 * @property string|null $scan_entity
 * @property string|null $audience_override
 * @property array<int, string>|null $channel_override
 * @property string|null $template_key_override
 * @property int $delay_minutes
 * @property string|null $throttle_key
 * @property int|null $throttle_window_hours
 * @property bool $is_active
 * @property int|null $estimated_monthly_cost_minor
 * @property string|null $estimated_monthly_currency
 * @property int $created_by
 * @property int|null $updated_by
 */
class AutomationRule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AutomationRuleFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'name', 'notification_key', 'trigger_type', 'event_name', 'schedule_cron',
        'scan_entity', 'audience_override', 'channel_override', 'template_key_override', 'delay_minutes',
        'throttle_key', 'throttle_window_hours', 'is_active', 'estimated_monthly_cost_minor',
        'estimated_monthly_currency', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'channel_override' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AutomationRuleFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<RuleCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(RuleCondition::class, 'rule_id');
    }

    /**
     * @return HasMany<RuleTemplateVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(RuleTemplateVariant::class, 'rule_id');
    }

    /**
     * BR-COM-02-008: never switched on blind.
     */
    public function hasReviewedCostEstimate(): bool
    {
        return $this->estimated_monthly_cost_minor !== null;
    }
}
