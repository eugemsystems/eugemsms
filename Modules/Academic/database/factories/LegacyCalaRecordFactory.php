<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LegacyCalaRecord;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<LegacyCalaRecord>
 */
class LegacyCalaRecordFactory extends Factory
{
    protected $model = LegacyCalaRecord::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'subject_id' => Subject::factory()->for($school),
            'cala_number' => 1,
            'title' => 'CALA 1: Practical Investigation',
            'raw_mark' => '18.00',
            'max_mark' => '20.00',
            'percent' => '90.00',
            'recorded_at' => now()->subYears(2),
            'source' => 'migrated',
        ];
    }
}
