<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ReportEntity;

/**
 * @extends Factory<ReportEntity>
 */
class ReportEntityFactory extends Factory
{
    protected $model = ReportEntity::class;

    public function definition(): array
    {
        return [
            'entity_key' => 'test_entity_'.$this->faker->unique()->numerify('###'),
            'module_code' => 'TEST',
            'base_model_class' => School::class,
            'default_school_scoped' => true,
            'allowed_join_entity_keys' => null,
        ];
    }
}
