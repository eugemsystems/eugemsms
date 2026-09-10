<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Permission;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $module = fake()->randomElement(['core', 'academic', 'finance']);
        $resource = fake()->word();
        $action = fake()->randomElement(['view', 'create', 'update', 'delete']);

        return [
            'name' => "{$module}.{$resource}.{$action}",
            'module_code' => strtoupper($module),
            'resource' => $resource,
            'action' => $action,
            'guard_name' => 'web',
            'is_dangerous' => false,
        ];
    }

    public function dangerous(): static
    {
        return $this->state(fn (array $attributes): array => ['is_dangerous' => true]);
    }
}
