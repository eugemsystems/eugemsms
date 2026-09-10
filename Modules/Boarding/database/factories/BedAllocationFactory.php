<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<BedAllocation>
 */
class BedAllocationFactory extends Factory
{
    protected $model = BedAllocation::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);
        $hostel = Hostel::factory()->for($school);
        $room = HostelRoom::factory()->state(['school_id' => $school, 'hostel_id' => $hostel]);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'bed_id' => HostelBed::factory()->state(['school_id' => $school, 'room_id' => $room]),
            'hostel_id' => $hostel,
            'room_id' => $room,
            'allocation_type' => 'initial',
            'effective_from' => now()->toDateString(),
            'status' => 'confirmed',
            'allocated_by' => User::factory(),
        ];
    }
}
