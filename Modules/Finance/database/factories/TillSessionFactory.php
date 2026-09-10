<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;

/**
 * @extends Factory<TillSession>
 */
class TillSessionFactory extends Factory
{
    protected $model = TillSession::class;

    public function definition(): array
    {
        $school = School::factory()->create();
        $year = AcademicYear::factory()->for($school)->create();

        return [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear')->create()->id,
            'till_id' => Till::factory()->create(['school_id' => $school->id])->id,
            'cashier_id' => User::factory(),
            'session_number' => 'TS/'.fake()->unique()->numerify('######'),
            'opened_at' => now(),
            'opening_float' => ['USD' => 5000],
            'status' => 'open',
        ];
    }
}
