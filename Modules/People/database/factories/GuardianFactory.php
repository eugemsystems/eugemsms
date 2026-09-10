<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'guardian_type' => 'individual',
            'title' => 'Mr',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'primary_phone' => '+263'.fake()->numerify('#########'),
            'email' => fake()->safeEmail(),
            'country' => 'ZW',
            'status' => 'active',
        ];
    }

    public function organisation(): self
    {
        return $this->state(fn (): array => [
            'guardian_type' => 'organisation',
            'title' => null,
            'first_name' => null,
            'last_name' => null,
            'organisation_name' => fake()->company(),
            'organisation_type' => 'employer',
        ]);
    }
}
