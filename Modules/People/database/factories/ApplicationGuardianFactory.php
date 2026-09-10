<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\People\Models\Application;
use Modules\People\Models\ApplicationGuardian;

/**
 * @extends Factory<ApplicationGuardian>
 */
class ApplicationGuardianFactory extends Factory
{
    protected $model = ApplicationGuardian::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'relationship' => 'father',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'primary_phone' => '+263'.fake()->numerify('#########'),
            'is_primary_contact' => true,
            'is_fee_responsible' => true,
        ];
    }
}
