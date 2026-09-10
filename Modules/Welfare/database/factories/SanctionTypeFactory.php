<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\SanctionType;

/**
 * @extends Factory<SanctionType>
 */
class SanctionTypeFactory extends Factory
{
    protected $model = SanctionType::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'DETENTION',
            'name' => 'Detention',
            'severity_level' => 2,
            'requires_guardian_meeting' => false,
            'requires_committee' => false,
            'removes_from_lessons' => false,
            'removes_from_campus' => false,
            'appealable' => true,
            'appeal_window_days' => 5,
            'is_active' => true,
        ];
    }

    public function suspension(): self
    {
        return $this->state(fn (): array => [
            'code' => 'SUSPENSION',
            'name' => 'Suspension',
            'severity_level' => 5,
            'requires_guardian_meeting' => true,
            'requires_committee' => true,
            'removes_from_lessons' => true,
            'removes_from_campus' => true,
            'max_duration_days' => 10,
        ]);
    }
}
