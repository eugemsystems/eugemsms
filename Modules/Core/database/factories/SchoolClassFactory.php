<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => 'Class '.fake()->unique()->lexify('??'),
            'stream_label' => fake()->randomElement(['Blue', 'Green', 'Red', null]),
            'capacity' => 40,
            'is_active' => true,
        ];
    }
}
