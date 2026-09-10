<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\ComplaintCategory;
use Modules\Core\Models\School;

/**
 * @extends Factory<ComplaintCategory>
 */
class ComplaintCategoryFactory extends Factory
{
    protected $model = ComplaintCategory::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'GEN_'.$this->faker->unique()->numerify('###'),
            'name' => 'General Complaint',
            'default_assignee_role_id' => null,
            'sla_hours' => 72,
            'is_safeguarding_trigger' => false,
        ];
    }

    public function safeguarding(): self
    {
        return $this->state(fn (): array => ['code' => 'SAFEGUARDING', 'name' => 'Safeguarding Concern', 'is_safeguarding_trigger' => true]);
    }
}
