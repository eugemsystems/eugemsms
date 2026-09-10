<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Boarding\Models\MovementLogEntry;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<MovementLogEntry>
 */
class MovementLogEntryFactory extends Factory
{
    protected $model = MovementLogEntry::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'checkpoint_id' => MovementCheckpoint::factory()->for($school),
            'direction' => 'out',
            'occurred_at' => now(),
            'method' => 'manual',
            'is_authorised' => true,
        ];
    }
}
