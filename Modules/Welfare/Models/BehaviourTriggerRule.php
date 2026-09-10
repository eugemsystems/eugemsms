<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Role;
use Modules\Welfare\Database\Factories\BehaviourTriggerRuleFactory;

/**
 * Book G BRD-07 §2/BR-BRD-07-003 — `is_automatic` defaults false.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $trigger_type
 * @property int|null $demerit_threshold
 * @property int|null $window_days
 * @property int|null $category_id
 * @property int|null $repeat_count
 * @property int $suggested_sanction_id
 * @property int|null $notify_role_id
 * @property bool $is_automatic
 * @property bool $is_active
 */
class BehaviourTriggerRule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BehaviourTriggerRuleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'name', 'trigger_type', 'demerit_threshold', 'window_days', 'category_id',
        'repeat_count', 'suggested_sanction_id', 'notify_role_id', 'is_automatic', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_automatic' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BehaviourTriggerRuleFactory::new();
    }

    /**
     * @return BelongsTo<BehaviourCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BehaviourCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<SanctionType, $this>
     */
    public function suggestedSanction(): BelongsTo
    {
        return $this->belongsTo(SanctionType::class, 'suggested_sanction_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function notifyRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'notify_role_id');
    }
}
