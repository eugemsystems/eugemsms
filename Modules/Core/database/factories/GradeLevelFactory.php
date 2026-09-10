<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;

/**
 * @extends Factory<GradeLevel>
 */
class GradeLevelFactory extends Factory
{
    protected $model = GradeLevel::class;

    public function definition(): array
    {
        // Global-unique (not per-school, since the DB constraint is
        // school_id+ordinal) — a range of 1-13 real grade levels
        // exhausts Faker's unique cache once enough tests create enough
        // grade levels in a single run (e.g. TenancyIsolationTest,
        // which creates one per registered tenant model per school).
        // Widened well past any real school's grade count while
        // staying inside smallint bounds; tests asserting a specific
        // pedagogical ordinal already override it explicitly.
        $grade = fake()->unique()->numberBetween(1, 32000);

        return [
            'school_id' => School::factory(),
            'section_id' => SchoolSection::factory(),
            'code' => 'G'.$grade,
            'name' => 'Grade '.$grade,
            'ordinal' => $grade,
            'is_exam_level' => false,
            'is_entry_level' => false,
            'is_exit_level' => false,
            'is_active' => true,
        ];
    }
}
