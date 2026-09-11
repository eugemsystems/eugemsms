<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Models\FeatureFlag;
use Modules\Saas\Database\Factories\FeatureRolloutFactory;

/**
 * Book J SAA-02 §2/BR-SAA-02-004/007. `Auditable` — every stage
 * advance is logged with `CORE-08`'s own rigour (§3, "the console
 * with the widest blast radius in the entire platform").
 *
 * @property int $id
 * @property string $feature_flag_key
 * @property string $rollout_stage
 * @property array<int, int>|null $pilot_tenant_ids
 * @property int|null $percentage
 * @property Carbon $started_at
 * @property int $started_by
 * @property string|null $notes
 */
class FeatureRollout extends Model
{
    use Auditable;

    /** @use HasFactory<FeatureRolloutFactory> */
    use HasFactory;

    public const array STAGE_ORDER = ['pilot', 'cohort', 'percentage', 'general'];

    protected $fillable = [
        'feature_flag_key', 'rollout_stage', 'pilot_tenant_ids', 'percentage', 'started_at', 'started_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'pilot_tenant_ids' => 'array',
            'percentage' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeatureRolloutFactory::new();
    }

    /**
     * @return BelongsTo<FeatureFlag, $this>
     */
    public function featureFlag(): BelongsTo
    {
        return $this->belongsTo(FeatureFlag::class, 'feature_flag_key', 'key');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
