<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\DiscountScheme;

/**
 * @extends Factory<DiscountScheme>
 */
class DiscountSchemeFactory extends Factory
{
    protected $model = DiscountScheme::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('SCHEME???')),
            'name' => fake()->words(3, true),
            'scheme_type' => 'automatic',
            'category' => 'sibling',
            'calculation_method' => 'percentage',
            'applies_to_components' => null,
            'default_percent' => '10.00',
            'default_amount_minor' => null,
            'currency' => 'USD',
            'tier_bands' => null,
            'requires_means_assessment' => false,
            'requires_academic_threshold' => false,
            'minimum_average_percent' => null,
            'requires_approval' => false,
            'is_sponsor_funded' => false,
            'contra_account_id' => Account::factory(),
            'renewal_frequency' => null,
            'is_active' => true,
        ];
    }

    public function schemeType(string $type): self
    {
        return $this->state(['scheme_type' => $type]);
    }

    public function sponsorFunded(): self
    {
        return $this->state(['is_sponsor_funded' => true, 'category' => 'corporate']);
    }

    public function requiresApproval(): self
    {
        return $this->state(['requires_approval' => true]);
    }
}
