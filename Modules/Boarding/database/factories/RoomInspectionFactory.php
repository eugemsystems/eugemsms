<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\RoomInspection;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * @extends Factory<RoomInspection>
 */
class RoomInspectionFactory extends Factory
{
    protected $model = RoomInspection::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);
        $hostel = Hostel::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'room_id' => HostelRoom::factory()->state(['school_id' => $school, 'hostel_id' => $hostel]),
            'inspection_date' => now()->toDateString(),
            'inspection_type' => 'routine',
            'criteria_scores' => ['tidiness' => 8, 'cleanliness' => 7],
            'max_score' => '20.00',
            'inspector_staff_id' => Staff::factory()->for($school),
            'follow_up_required' => false,
        ];
    }
}
