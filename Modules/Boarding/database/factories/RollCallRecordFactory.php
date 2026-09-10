<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<RollCallRecord>
 */
class RollCallRecordFactory extends Factory
{
    protected $model = RollCallRecord::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'roll_call_id' => RollCall::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'roll_date' => now()->toDateString(),
            'status' => 'present',
            'is_auto_populated' => false,
            'marked_at' => now(),
        ];
    }
}
