<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Visitor;
use Modules\Core\Models\School;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'full_name' => fake()->name(),
            'id_type' => 'national_id',
            'is_blacklisted' => false,
            'is_watchlisted' => false,
        ];
    }
}
