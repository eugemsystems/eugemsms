<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableGenerationRun;
use Modules\Core\Models\School;

/**
 * @extends Factory<TimetableGenerationRun>
 */
class TimetableGenerationRunFactory extends Factory
{
    protected $model = TimetableGenerationRun::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'timetable_id' => Timetable::factory()->for($school),
            'algorithm' => 'greedy',
            'status' => 'queued',
            'requested_by' => User::factory(),
        ];
    }
}
