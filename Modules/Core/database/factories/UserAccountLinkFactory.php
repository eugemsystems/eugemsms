<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\UserAccountLink;

/**
 * @extends Factory<UserAccountLink>
 */
class UserAccountLinkFactory extends Factory
{
    protected $model = UserAccountLink::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'linked_type' => 'student',
            'linked_id' => fake()->numberBetween(1, 100000),
            'is_active' => true,
        ];
    }
}
