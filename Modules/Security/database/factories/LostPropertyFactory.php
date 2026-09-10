<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Security\Models\LostProperty;

/**
 * @extends Factory<LostProperty>
 */
class LostPropertyFactory extends Factory
{
    protected $model = LostProperty::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'found_on' => now()->toDateString(),
            'description' => 'Blue water bottle',
            'status' => 'held',
        ];
    }
}
