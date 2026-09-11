<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\SchemeBudgetEnvelope;

/**
 * @extends Factory<SchemeBudgetEnvelope>
 */
class SchemeBudgetEnvelopeFactory extends Factory
{
    protected $model = SchemeBudgetEnvelope::class;

    public function definition(): array
    {
        return [
            'scheme_id' => DiscountScheme::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'budget_minor' => 2_000_000,
            'currency' => 'USD',
            'committed_minor' => 0,
            'utilised_minor' => 0,
        ];
    }

    public function uncapped(): self
    {
        return $this->state(['budget_minor' => null, 'currency' => null]);
    }
}
