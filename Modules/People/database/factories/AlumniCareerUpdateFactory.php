<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\People\Models\AlumniCareerUpdate;
use Modules\People\Models\Alumnus;

/**
 * @extends Factory<AlumniCareerUpdate>
 */
class AlumniCareerUpdateFactory extends Factory
{
    protected $model = AlumniCareerUpdate::class;

    public function definition(): array
    {
        $alumnus = Alumnus::factory()->create();

        return [
            'school_id' => $alumnus->school_id,
            'alumnus_id' => $alumnus->id,
            'update_type' => 'employment',
            'title' => 'Software Engineer',
            'institution_or_employer' => 'Example Corp',
            'is_current' => true,
            'verified' => false,
            'submitted_at' => now(),
        ];
    }
}
