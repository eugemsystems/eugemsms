<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\School;

/**
 * @extends Factory<CustomFieldDefinition>
 */
class CustomFieldDefinitionFactory extends Factory
{
    protected $model = CustomFieldDefinition::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'entity_type' => 'student',
            'key' => 'field_'.fake()->unique()->numberBetween(1, 100000),
            'label' => fake()->words(2, true),
            'data_type' => 'text',
            'is_required' => false,
            'is_searchable' => false,
            'is_exposed_in_api' => true,
            'is_printable' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
