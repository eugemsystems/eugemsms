<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\CustomFieldValue;
use Modules\Core\Models\School;

/**
 * @extends Factory<CustomFieldValue>
 */
class CustomFieldValueFactory extends Factory
{
    protected $model = CustomFieldValue::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'definition_id' => CustomFieldDefinition::factory(),
            'entity_type' => 'student',
            'entity_id' => 1,
            'value_text' => fake()->word(),
        ];
    }
}
