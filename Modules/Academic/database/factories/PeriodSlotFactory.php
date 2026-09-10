<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\PeriodStructure;
use Modules\Core\Models\School;

/**
 * @extends Factory<PeriodSlot>
 */
class PeriodSlotFactory extends Factory
{
    protected $model = PeriodSlot::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'structure_id' => PeriodStructure::factory()->for($school),
            'cycle_day' => 1,
            'period_number' => 1,
            'label' => 'Period 1',
            'slot_type' => 'teaching',
            'starts_at' => '07:30',
            'ends_at' => '08:10',
            'duration_minutes' => 40,
            'is_teachable' => true,
            'requires_attendance' => true,
            'sort_order' => 1,
        ];
    }
}
