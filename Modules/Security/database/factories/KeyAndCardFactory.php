<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Security\Models\KeyAndCard;

/**
 * @extends Factory<KeyAndCard>
 */
class KeyAndCardFactory extends Factory
{
    protected $model = KeyAndCard::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'identifier' => 'KEY-'.fake()->unique()->numberBetween(1, 99999),
            'item_type' => 'key',
            'description' => 'Staff room key',
            'is_master' => false,
            'status' => 'available',
        ];
    }

    public function master(): self
    {
        return $this->state(fn (): array => ['is_master' => true, 'description' => 'Master key — all classrooms']);
    }
}
