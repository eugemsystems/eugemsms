<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\TimetableException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<TimetableException>
 */
class TimetableExceptionFactory extends Factory
{
    protected $model = TimetableException::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'exception_date' => now()->toDateString(),
            'exception_type' => 'no_lessons',
            'affected_scope' => 'whole_school',
            'reason' => 'Public holiday',
            'suppresses_attendance' => true,
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
