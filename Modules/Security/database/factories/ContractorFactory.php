<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Security\Models\Contractor;

/**
 * @extends Factory<Contractor>
 */
class ContractorFactory extends Factory
{
    protected $model = Contractor::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'company_name' => 'Reliable Builders Pvt Ltd',
            'work_type' => 'construction',
            'status' => 'pending',
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'insurance_expires_on' => now()->addMonths(6)->toDateString(),
            'safety_induction_on' => now()->toDateString(),
            'induction_valid_until' => now()->addYear()->toDateString(),
        ]);
    }
}
