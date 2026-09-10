<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffExitChecklist;

/**
 * @extends Factory<StaffExitChecklist>
 */
class StaffExitChecklistFactory extends Factory
{
    protected $model = StaffExitChecklist::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'initiated_at' => now(),
            'initiated_by' => User::factory(),
            'items' => array_map(
                fn (array $item): array => [...$item, 'is_cleared' => false, 'cleared_by' => null, 'cleared_at' => null],
                StaffExitChecklist::DEFAULT_ITEMS,
            ),
        ];
    }
}
