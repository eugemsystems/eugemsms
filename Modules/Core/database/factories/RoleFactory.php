<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Role;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'school_id' => null,
            'name' => $name,
            'display_name' => ucwords(str_replace('-', ' ', $name)),
            'guard_name' => 'web',
            'is_system' => false,
            'is_vendor_only' => false,
            'category' => 'custom',
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes): array => ['is_system' => true, 'school_id' => null]);
    }

    public function forSchool(int $schoolId): static
    {
        return $this->state(fn (array $attributes): array => ['school_id' => $schoolId]);
    }
}
