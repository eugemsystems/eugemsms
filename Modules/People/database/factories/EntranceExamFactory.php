<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\EntranceExam;
use Modules\People\Models\Intake;

/**
 * @extends Factory<EntranceExam>
 */
class EntranceExamFactory extends Factory
{
    protected $model = EntranceExam::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'intake_id' => Intake::factory()->for($school), 'name' => 'Grade 7 entrance', 'exam_date' => now()->addWeek()->toDateString(), 'start_time' => '09:00', 'papers' => [['subject' => 'Mathematics', 'max_mark' => 100, 'weight' => 50], ['subject' => 'English', 'max_mark' => 100, 'weight' => 50]], 'status' => 'scheduled',
        ];
    }
}
