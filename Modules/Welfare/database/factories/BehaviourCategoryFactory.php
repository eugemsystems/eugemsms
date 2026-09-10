<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\BehaviourCategory;

/**
 * @extends Factory<BehaviourCategory>
 */
class BehaviourCategoryFactory extends Factory
{
    protected $model = BehaviourCategory::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'HOMEWORK',
            'name' => 'Homework not done',
            'polarity' => 'negative',
            'default_points' => -3,
            'severity_level' => 1,
            'is_safeguarding_trigger' => false,
            'is_active' => true,
        ];
    }

    public function positive(): self
    {
        return $this->state(fn (): array => [
            'code' => 'MERIT', 'name' => 'Merit award', 'polarity' => 'positive', 'default_points' => 5, 'severity_level' => null,
        ]);
    }

    public function safeguardingTrigger(): self
    {
        return $this->state(fn (): array => [
            'code' => 'ABSCONDING', 'name' => 'Absconding', 'polarity' => 'negative', 'default_points' => 0,
            'severity_level' => 5, 'is_safeguarding_trigger' => true,
        ]);
    }
}
