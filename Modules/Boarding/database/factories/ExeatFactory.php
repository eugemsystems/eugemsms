<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<Exeat>
 */
class ExeatFactory extends Factory
{
    protected $model = Exeat::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'exeat_number' => 'EX-'.fake()->unique()->numerify('######'),
            'student_id' => Student::factory()->for($school),
            'exeat_type_id' => ExeatType::factory()->for($school),
            'request_source' => 'guardian_portal',
            'reason' => 'Weekend visit home.',
            'departs_at' => now()->addDay(),
            'returns_by' => now()->addDays(2),
            'destination_address' => '12 Baines Avenue, Harare',
            'destination_country' => 'ZW',
            'contact_phone' => '+263771234567',
            'collection_method' => 'guardian_collect',
            'status' => 'pending',
        ];
    }
}
