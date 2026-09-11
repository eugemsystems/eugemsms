<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Saas\Models\OnboardingTemplate;

/**
 * @extends Factory<OnboardingTemplate>
 */
class OnboardingTemplateFactory extends Factory
{
    protected $model = OnboardingTemplate::class;

    public function definition(): array
    {
        return [
            'configuration_profile_id' => ConfigurationProfile::factory(),
            'template_name' => 'Boarding Secondary Starter',
            'suited_for' => 'boarding_secondary',
            'used_count' => 0,
        ];
    }
}
