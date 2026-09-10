<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Stores\Models\Budget;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'name' => 'Operating Budget',
            'budget_type' => 'operating',
            'period_basis' => 'annual',
            'currency' => 'USD',
            'version' => 1,
            'status' => 'draft',
            'prepared_by' => User::factory(),
        ];
    }
}
