<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\PeriodStateTransitionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;

/**
 * Book A CORE-03 §2 — BR-CORE-03-009: an immutable audit row written by
 * every `ACT-TransitionPeriodState` call. No `updated_at` — this table
 * has no update path, only inserts (`$timestamps = false`; `occurred_at`
 * is the row's only point-in-time column, set explicitly).
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property PeriodType $period_type
 * @property PeriodState $from_state
 * @property PeriodState $to_state
 * @property string|null $reason
 * @property int $performed_by
 * @property int|null $approved_by
 * @property string|null $ip_address
 * @property Carbon $occurred_at
 * @property-read Term $term
 * @property-read User $performer
 * @property-read User|null $approver
 */
class PeriodStateTransition extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PeriodStateTransitionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'term_id',
        'period_type',
        'from_state',
        'to_state',
        'reason',
        'performed_by',
        'approved_by',
        'ip_address',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'period_type' => PeriodType::class,
            'from_state' => PeriodState::class,
            'to_state' => PeriodState::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PeriodStateTransitionFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
