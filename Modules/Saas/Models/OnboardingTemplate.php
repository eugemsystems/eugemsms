<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Saas\Database\Factories\OnboardingTemplateFactory;

/**
 * Book J SAA-03 §2/BR-SAA-03-002. `onboarding_template_library` — a
 * curated name/suitability tag over an existing `CORE-04`
 * `ConfigurationProfile`, never a second copy of its payload.
 *
 * @property int $id
 * @property int $configuration_profile_id
 * @property string $template_name
 * @property string|null $suited_for
 * @property int $used_count
 */
class OnboardingTemplate extends Model
{
    /** @use HasFactory<OnboardingTemplateFactory> */
    use HasFactory;

    protected $table = 'onboarding_template_library';

    protected $fillable = [
        'configuration_profile_id', 'template_name', 'suited_for', 'used_count',
    ];

    protected function casts(): array
    {
        return [
            'used_count' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return OnboardingTemplateFactory::new();
    }

    /**
     * @return BelongsTo<ConfigurationProfile, $this>
     */
    public function configurationProfile(): BelongsTo
    {
        return $this->belongsTo(ConfigurationProfile::class);
    }
}
