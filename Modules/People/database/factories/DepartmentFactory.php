<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Department;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => fake()->unique()->lexify('DEPT-????'),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['academic', 'administrative', 'operational']),
            'is_active' => true,
        ];
    }
}
