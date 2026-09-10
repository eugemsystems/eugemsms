<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Reporting\Models\ReportDefinition;

/**
 * @extends Factory<ReportDefinition>
 */
class ReportDefinitionFactory extends Factory
{
    protected $model = ReportDefinition::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'INCOME_STATEMENT',
            'name' => 'Income Statement',
            'report_type' => 'income_statement',
            'structure' => [],
            'comparative_periods' => 1,
            'show_variance' => true,
            'show_budget' => false,
            'is_system' => true,
        ];
    }
}
