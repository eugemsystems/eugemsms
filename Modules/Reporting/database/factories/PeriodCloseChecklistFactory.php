<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Reporting\Models\PeriodCloseChecklist;

/**
 * @extends Factory<PeriodCloseChecklist>
 */
class PeriodCloseChecklistFactory extends Factory
{
    protected $model = PeriodCloseChecklist::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => function (array $attributes): int {
                $year = AcademicYear::factory()->create(['school_id' => $attributes['school_id']]);

                return Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $year->id])->id;
            },
            'period_type' => 'financial',
            'run_at' => now(),
            'run_by' => User::factory(),
            'overall_status' => 'passed',
            'results' => [],
        ];
    }
}
