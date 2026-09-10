<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'staff_number' => 'STF/'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-21 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'nationality' => 'ZW',
            'primary_phone' => fake()->numerify('+263#########'),
            'staff_category' => 'teaching',
            'joined_on' => now()->toDateString(),
            'status' => 'active',
            'is_teaching' => false,
        ];
    }

    public function teaching(): self
    {
        return $this->state(fn (): array => [
            'is_teaching' => true,
            'staff_category' => 'teaching',
            'max_weekly_periods' => 30,
        ]);
    }
}
