<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\EstablishmentPost;

/**
 * @extends Factory<EstablishmentPost>
 */
class EstablishmentPostFactory extends Factory
{
    protected $model = EstablishmentPost::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => fake()->jobTitle(),
            'approved_count' => 1,
            'filled_count' => 0,
            'is_teaching' => false,
            'is_active' => true,
        ];
    }

    public function teaching(): self
    {
        return $this->state(fn (): array => ['is_teaching' => true]);
    }
}
