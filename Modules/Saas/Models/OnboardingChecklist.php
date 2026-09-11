<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Database\Factories\OnboardingChecklistFactory;

/**
 * Book J SAA-03 §2/BR-SAA-03-001. Not `BelongsToSchool` — read by the
 * vendor's onboarding tracker across every tenant, the same
 * cross-tenant-visibility shape every other `SAA-*` table uses.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $school_id
 * @property Carbon $started_at
 * @property Carbon|null $target_go_live_date
 * @property array<int, array{key: string, label: string, completed_at: string|null, owner: string|null}> $steps
 * @property string $status
 * @property int|null $assigned_success_manager
 */
class OnboardingChecklist extends Model
{
    /** @use HasFactory<OnboardingChecklistFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'school_id', 'started_at', 'target_go_live_date', 'steps', 'status', 'assigned_success_manager',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'target_go_live_date' => 'date',
            'steps' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return OnboardingChecklistFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function successManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_success_manager');
    }

    public function isFullyComplete(): bool
    {
        foreach ($this->steps as $step) {
            if (($step['completed_at'] ?? null) === null) {
                return false;
            }
        }

        return true;
    }
}
