<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Comms\Database\Factories\RuleConditionFactory;

/**
 * Book I COM-02 §2/BR-COM-02-003/004.
 *
 * @property int $id
 * @property int $rule_id
 * @property int $group_id
 * @property string $group_logic
 * @property string $field
 * @property string $operator
 * @property mixed $value
 * @property int|null $sort_order
 */
class RuleCondition extends Model
{
    /** @use HasFactory<RuleConditionFactory> */
    use HasFactory;

    protected $fillable = [
        'rule_id', 'group_id', 'group_logic', 'field', 'operator', 'value', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RuleConditionFactory::new();
    }

    /**
     * @return BelongsTo<AutomationRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}
