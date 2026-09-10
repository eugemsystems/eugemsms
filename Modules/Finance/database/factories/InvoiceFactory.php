<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Student;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'invoice_number' => 'INV/'.fake()->unique()->numerify('######'),
            'invoice_type' => 'term',
            'student_id' => Student::factory()->for($school),
            'billed_party_type' => 'guardian',
            'billed_party_id' => 1,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'gross_minor' => 10000,
            'net_minor' => 10000,
            'balance_minor' => 10000,
            'currency' => 'USD',
            'status' => 'issued',
        ];
    }
}
