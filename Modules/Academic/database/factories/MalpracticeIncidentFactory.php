<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\MalpracticeIncident;
use Modules\Core\Models\School;

/**
 * @extends Factory<MalpracticeIncident>
 */
class MalpracticeIncidentFactory extends Factory
{
    protected $model = MalpracticeIncident::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'session_id' => ExaminationSession::factory()->for($school),
            'incident_type' => 'unauthorised_material',
            'description' => 'Candidate found with unauthorised notes.',
            'reported_by' => User::factory(),
            'occurred_at' => now(),
            'status' => 'reported',
            'is_confidential' => true,
        ];
    }
}
