<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Book A CORE-07 §2. Not `BelongsToSchool` — scoped through its parent
 * chain, which already carries `school_id`.
 *
 * @property int $id
 * @property int $chain_id
 * @property int $step_number
 * @property string $name
 * @property string $approver_type
 * @property int|null $approver_role_id
 * @property int|null $approver_user_id
 * @property string|null $dynamic_resolver
 * @property string $mode
 * @property int $required_approvals
 * @property array<int, array{field: string, operator: string, value: mixed}>|null $condition_rules
 * @property int|null $escalate_after_hours
 * @property int|null $escalate_to_role_id
 * @property bool $can_reject
 * @property bool $can_return
 * @property bool $requires_comment
 */
class ApprovalStep extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'chain_id', 'step_number', 'name', 'approver_type', 'approver_role_id',
        'approver_user_id', 'dynamic_resolver', 'mode', 'required_approvals',
        'condition_rules', 'escalate_after_hours', 'escalate_to_role_id',
        'can_reject', 'can_return', 'requires_comment',
    ];

    protected function casts(): array
    {
        return [
            'condition_rules' => 'array',
            'can_reject' => 'boolean',
            'can_return' => 'boolean',
            'requires_comment' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ApprovalChain, $this>
     */
    public function chain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class, 'chain_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function escalateToRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'escalate_to_role_id');
    }
}
