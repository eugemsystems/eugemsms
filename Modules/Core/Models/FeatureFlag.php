<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\FeatureFlagFactory;

/**
 * Book A CORE-04 §2.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property bool $is_globally_enabled
 * @property int $rollout_percentage
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, FeatureFlagOverride> $overrides
 */
class FeatureFlag extends Model
{
    /** @use HasFactory<FeatureFlagFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_globally_enabled',
        'rollout_percentage',
    ];

    protected function casts(): array
    {
        return [
            'is_globally_enabled' => 'boolean',
            'rollout_percentage' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeatureFlagFactory::new();
    }

    /**
     * @return HasMany<FeatureFlagOverride, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(FeatureFlagOverride::class);
    }
}
