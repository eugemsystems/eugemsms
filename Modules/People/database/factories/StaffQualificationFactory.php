<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffQualification;

/**
 * @extends Factory<StaffQualification>
 */
class StaffQualificationFactory extends Factory
{
    protected $model = StaffQualification::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school), 'qualification_type' => 'degree', 'title' => 'BEd Mathematics', 'institution' => 'University of Zimbabwe', 'country' => 'ZW',
        ];
    }
}
