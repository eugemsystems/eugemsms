<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSeating;
use Modules\Academic\Models\Venue;
use Modules\Core\Models\School;

/**
 * @extends Factory<ExaminationSeating>
 */
class ExaminationSeatingFactory extends Factory
{
    protected $model = ExaminationSeating::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'paper_id' => ExaminationPaper::factory()->for($school),
            'candidate_id' => ExaminationCandidate::factory()->for($school),
            'venue_id' => Venue::factory()->for($school),
            'seat_number' => 'A1',
        ];
    }
}
