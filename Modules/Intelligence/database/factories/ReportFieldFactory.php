<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intelligence\Models\ReportField;

/**
 * @extends Factory<ReportField>
 */
class ReportFieldFactory extends Factory
{
    protected $model = ReportField::class;

    public function definition(): array
    {
        return [
            'module_code' => 'TEST',
            'entity_key' => 'test_entity',
            'field_key' => 'field_'.$this->faker->unique()->numerify('###'),
            'label' => 'Test Field',
            'data_type' => 'string',
            'is_filterable' => true,
            'is_groupable' => true,
            'is_aggregatable' => false,
            'required_permission' => 'test.field.view',
            'is_sensitive' => false,
            'enum_options' => null,
        ];
    }
}
