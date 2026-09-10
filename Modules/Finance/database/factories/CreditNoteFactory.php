<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CreditNote;
use Modules\People\Models\Student;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'credit_note_number' => 'CN/'.fake()->unique()->numerify('######'),
            'student_id' => Student::factory()->for($school),
            'reason_code' => 'goodwill',
            'reason' => 'Goodwill adjustment',
            'amount_minor' => 1000,
            'currency' => 'USD',
            'issue_date' => now()->toDateString(),
            'status' => 'issued',
            'raised_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
