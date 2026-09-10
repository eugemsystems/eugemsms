<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\SubjectGroup;
use Modules\Core\Models\School;

/**
 * @extends Factory<SubjectGroup>
 */
class SubjectGroupFactory extends Factory
{
    protected $model = SubjectGroup::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'GEN_'.fake()->unique()->numerify('###'),
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
