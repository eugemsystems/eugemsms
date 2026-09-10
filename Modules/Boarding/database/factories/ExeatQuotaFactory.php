<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\ExeatQuota;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<ExeatQuota>
 */
class ExeatQuotaFactory extends Factory
{
    protected $model = ExeatQuota::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'exeat_type_id' => ExeatType::factory()->for($school),
            'allowed' => 3,
            'used' => 0,
            'pending' => 0,
        ];
    }
}
