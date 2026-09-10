<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolModule;

/**
 * @extends Factory<SchoolModule>
 */
class SchoolModuleFactory extends Factory
{
    protected $model = SchoolModule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'module_code' => fake()->randomElement(['CORE', 'FIN', 'PPL', 'ACA', 'BRD']),
            'is_enabled' => true,
            'enabled_at' => now(),
            'enabled_by' => null,
            'expires_at' => null,
            'config' => [],
        ];
    }
}
