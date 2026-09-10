<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\SettingDefinition;

/**
 * @extends Factory<SettingDefinition>
 */
class SettingDefinitionFactory extends Factory
{
    protected $model = SettingDefinition::class;

    public function definition(): array
    {
        return [
            'key' => 'test.'.fake()->unique()->word(),
            'module_code' => 'CORE-04',
            'group_key' => 'general',
            'label' => fake()->words(2, true),
            'data_type' => 'string',
            'default_value' => 'default',
            'ui_control' => 'text',
            'lowest_scope' => 'user',
            'is_encrypted' => false,
            'sort_order' => 0,
        ];
    }
}
