<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\CustomReport;

/**
 * @extends Factory<CustomReport>
 */
class CustomReportFactory extends Factory
{
    protected $model = CustomReport::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Test Report',
            'description' => null,
            'primary_entity_key' => 'student',
            'selected_fields' => [['entity' => 'student', 'field' => 'first_name']],
            'filters' => null,
            'group_by' => null,
            'aggregations' => null,
            'sort' => null,
            'chart_type' => 'table',
            'created_by' => User::factory(),
            'is_active' => true,
        ];
    }
}
