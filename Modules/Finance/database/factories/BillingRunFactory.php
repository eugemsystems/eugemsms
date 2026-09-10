<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\BillingRun;

/**
 * @extends Factory<BillingRun>
 */
class BillingRunFactory extends Factory
{
    protected $model = BillingRun::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'status' => 'computing',
            'computed_by' => User::factory(),
        ];
    }
}
