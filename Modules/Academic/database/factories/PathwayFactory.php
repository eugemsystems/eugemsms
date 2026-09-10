<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\Pathway;
use Modules\Core\Models\School;

/**
 * @extends Factory<Pathway>
 */
class PathwayFactory extends Factory
{
    protected $model = Pathway::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'framework_id' => CurriculumFramework::factory()->for($school),
            'code' => 'ACADEMIC',
            'name' => 'Academic',
            'applies_from_level_ordinal' => 8,
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
