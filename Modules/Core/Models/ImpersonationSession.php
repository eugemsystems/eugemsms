<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-05 §2/BR-CORE-05-017..019. The session envelope only; each
 * action taken while impersonating is separately audited elsewhere as
 * `performed_by = impersonated, on_behalf_of = impersonator`.
 *
 * @property int $id
 * @property int $impersonator_id
 * @property int $impersonated_id
 * @property int|null $school_id
 * @property string $reason
 * @property string|null $ticket_reference
 * @property string|null $consent_reference
 * @property Carbon $started_at
 * @property Carbon $expires_at
 * @property Carbon|null $ended_at
 * @property array<int, array<string, mixed>>|null $actions_performed
 */
class ImpersonationSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'impersonator_id', 'impersonated_id', 'school_id', 'reason', 'ticket_reference',
        'consent_reference', 'started_at', 'expires_at', 'ended_at', 'actions_performed',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
            'actions_performed' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_id');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }

    public function recordBlockedAction(string $description): void
    {
        $this->actions_performed = [...($this->actions_performed ?? []), [
            'blocked' => true,
            'description' => $description,
            'at' => Carbon::now()->toIso8601String(),
        ]];

        $this->save();
    }
}
