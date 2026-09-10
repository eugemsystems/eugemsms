<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\SurveyQuestion;
use Modules\Comms\Models\SurveyResponse;
use Modules\Comms\Models\SurveyResponseAnswer;

/**
 * @extends Factory<SurveyResponseAnswer>
 */
class SurveyResponseAnswerFactory extends Factory
{
    protected $model = SurveyResponseAnswer::class;

    public function definition(): array
    {
        return [
            'response_id' => SurveyResponse::factory(),
            'question_id' => SurveyQuestion::factory(),
            'answer_value' => 5,
        ];
    }
}
