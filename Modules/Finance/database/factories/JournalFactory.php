<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Journal;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    protected $model = Journal::class;

    public function definition(): array
    {
        $school = School::factory()->create();
        $year = AcademicYear::factory()->for($school)->create();
        $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

        return [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'term_id' => $term->id,
            'journal_number' => 'JNL/'.fake()->unique()->numerify('######'),
            'journal_type' => 'MANUAL',
            'narration' => fake()->sentence(),
            'effective_at' => now()->toDateString(),
            'posted_at' => now(),
            'status' => 'posted',
            'posted_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
