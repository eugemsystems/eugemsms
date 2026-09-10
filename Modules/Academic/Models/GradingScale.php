<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Database\Factories\GradingScaleFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book D ACA-05 §2/BR-ACA-05-001/003.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $framework_id
 * @property string $code
 * @property string $name
 * @property string $scale_type
 * @property bool $lower_is_better
 * @property string|null $pass_grade
 * @property array<int, int>|null $is_default_for_level
 * @property bool $is_active
 */
class GradingScale extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GradingScaleFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'framework_id', 'code', 'name', 'scale_type', 'lower_is_better',
        'pass_grade', 'is_default_for_level', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lower_is_better' => 'boolean',
            'is_default_for_level' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GradingScaleFactory::new();
    }

    /**
     * @return BelongsTo<CurriculumFramework, $this>
     */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(CurriculumFramework::class, 'framework_id');
    }

    /**
     * @return HasMany<GradeBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderBy('sort_order');
    }

    /**
     * BR-ACA-05-003: with `lower_is_better`, the band whose range
     * contains `$percent` is still found the same way — only ranking/
     * aggregation elsewhere inverts, not band lookup itself.
     *
     * `max_percent` is exclusive except on the top band (the one
     * ending at 100) — see `CreateGradingScaleAction`'s contiguity
     * rule: bands touch edge to edge, so a value exactly on a shared
     * boundary belongs to the band above it, never both.
     */
    public function bandFor(float $percent): ?GradeBand
    {
        return $this->bands->first(function (GradeBand $band) use ($percent): bool {
            if ($percent < (float) $band->min_percent) {
                return false;
            }

            $isTopBand = (float) $band->max_percent >= 100.0;

            return $isTopBand ? $percent <= (float) $band->max_percent : $percent < (float) $band->max_percent;
        });
    }
}
