<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\Survey;
use Modules\Comms\Models\SurveyResponse;
use Modules\Core\Models\School;

/**
 * @extends Factory<SurveyResponse>
 */
class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'survey_id' => Survey::factory()->for($school),
            'respondent_type' => 'guardian',
            'respondent_id' => null,
            'submitted_at' => now(),
        ];
    }
}
