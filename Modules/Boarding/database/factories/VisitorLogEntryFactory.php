<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Visitor;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Models\School;

/**
 * @extends Factory<VisitorLogEntry>
 */
class VisitorLogEntryFactory extends Factory
{
    protected $model = VisitorLogEntry::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'visitor_id' => Visitor::factory()->for($school),
            'visit_purpose' => 'parent_visit',
            'signed_in_at' => now(),
            'gate_staff_in' => User::factory(),
            'induction_completed' => false,
        ];
    }
}
