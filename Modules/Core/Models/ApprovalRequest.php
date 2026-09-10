<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ApprovalRequestFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-07 §2/BR-CORE-07-013. Session-bound (carries
 * `academic_year_id`/`term_id`) but never guarded by `PeriodGuard` — a
 * request survives a term lock with its full history intact.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int|null $term_id
 * @property int $chain_id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property int $current_step
 * @property string $status
 * @property string $title
 * @property string|null $summary
 * @property int|null $amount_minor
 * @property string|null $amount_currency
 * @property int $requested_by
 * @property Carbon $requested_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $due_at
 */
class ApprovalRequest extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'chain_id', 'approvable_type',
        'approvable_id', 'current_step', 'status', 'title', 'summary', 'amount_minor',
        'amount_currency', 'requested_by', 'requested_at', 'completed_at', 'due_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ApprovalRequestFactory::new();
    }

    /**
     * @return BelongsTo<ApprovalChain, $this>
     */
    public function chain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class, 'chain_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The approvable model is very often itself `BelongsToSchool`
     * (via whatever concrete type it is), whose global scope would
     * otherwise filter this lookup to the ambient `SchoolContext` —
     * unset for most of the Actions that call this, since they act on
     * an explicit request id rather than a browsing session. Same
     * `withoutGlobalScopes()` escape hatch as everywhere else a
     * BelongsToSchool model is reached by explicit id rather than an
     * ambient-scoped query (see `.ai/rules/core.md`).
     */
    public function resolveApprovable(): Model
    {
        return $this->approvable()->withoutGlobalScopes()->firstOrFail();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'request_id')->orderBy('acted_at');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function currentStep(): ?ApprovalStep
    {
        return ApprovalStep::where('chain_id', $this->chain_id)->where('step_number', $this->current_step)->first();
    }
}
