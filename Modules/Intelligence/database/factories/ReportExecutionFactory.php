<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ReportExecution;

/**
 * @extends Factory<ReportExecution>
 */
class ReportExecutionFactory extends Factory
{
    protected $model = ReportExecution::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'report_id' => null,
            'executed_by' => User::factory(),
            'row_count' => 10,
            'duration_ms' => 120,
            'schools_included' => null,
            'executed_at' => now(),
        ];
    }
}
