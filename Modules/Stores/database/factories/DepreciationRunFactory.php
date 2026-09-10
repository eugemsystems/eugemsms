<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Stores\Models\DepreciationRun;

/**
 * @extends Factory<DepreciationRun>
 */
class DepreciationRunFactory extends Factory
{
    protected $model = DepreciationRun::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $attributes['academic_year_id']])->id,
            'period_month' => now()->format('Y-m'),
            'run_date' => now()->toDateString(),
            'currency' => 'USD',
            'status' => 'preview',
            'computed_by' => User::factory(),
        ];
    }
}
