<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Application;
use Modules\People\Models\Interview;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'application_id' => Application::factory()->for($school), 'scheduled_at' => now()->addDays(3), 'panel_user_ids' => [],
        ];
    }
}
