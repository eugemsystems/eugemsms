<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\RuleExecutionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Notification;

/**
 * Book I COM-02 §2/BR-COM-02-006 — APPEND-ONLY.
 *
 * @property int $id
 * @property int $school_id
 * @property int $rule_id
 * @property string $trigger_source
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property bool $matched
 * @property string|null $skip_reason
 * @property int|null $notification_id
 * @property string|null $variant_key
 * @property Carbon $executed_at
 */
class RuleExecution extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RuleExecutionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'rule_id', 'trigger_source', 'subject_type', 'subject_id', 'matched',
        'skip_reason', 'notification_id', 'variant_key', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'matched' => 'boolean',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RuleExecutionFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('rule_executions is append-only — an execution record is never edited.');
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('rule_executions is append-only — an execution record is never deleted.');
        });
    }

    /**
     * @return BelongsTo<AutomationRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }

    /**
     * @return BelongsTo<Notification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
