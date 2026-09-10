<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\EscalationActionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book F BRD-02 §2/§3 ⭐ — APPEND-ONLY, per BR-BRD-02-013. Every
 * notification, acknowledgement, mandatory action record, escalation
 * and resolution against an incident is its own new row, never an
 * update to a prior one — mirrors `ScriptCustodyLogEntry`'s guard.
 *
 * @property int $id
 * @property int $school_id
 * @property int $incident_id
 * @property int $step_number
 * @property string $action_type
 * @property int|null $actor_id
 * @property string|null $action_taken
 * @property Carbon $occurred_at
 */
class EscalationAction extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EscalationActionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'incident_id', 'step_number', 'action_type', 'actor_id', 'action_taken', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EscalationActionFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('escalation_actions is append-only — an entry is never amended.', []);
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('escalation_actions is append-only — an entry is never deleted.', []);
        });
    }

    /**
     * @return BelongsTo<MissingLearnerIncident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(MissingLearnerIncident::class, 'incident_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
