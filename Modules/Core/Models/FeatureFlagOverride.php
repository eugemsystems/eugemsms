<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\FeatureFlagOverrideFactory;

/**
 * Book A CORE-04 §2/BR-CORE-04-016 — `scope_type` here is deliberately
 * narrower than settings' `SettingScope`: only `user`, `school`, and
 * `tenant`, matching the resolution order the spec defines.
 *
 * @property int $id
 * @property int $feature_flag_id
 * @property string $scope_type
 * @property int $scope_id
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FeatureFlag $featureFlag
 */
class FeatureFlagOverride extends Model
{
    /** @use HasFactory<FeatureFlagOverrideFactory> */
    use HasFactory;

    protected $fillable = [
        'feature_flag_id',
        'scope_type',
        'scope_id',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeatureFlagOverrideFactory::new();
    }

    /**
     * @return BelongsTo<FeatureFlag, $this>
     */
    public function featureFlag(): BelongsTo
    {
        return $this->belongsTo(FeatureFlag::class);
    }
}
