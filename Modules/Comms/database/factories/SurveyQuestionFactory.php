<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\Survey;
use Modules\Comms\Models\SurveyQuestion;

/**
 * @extends Factory<SurveyQuestion>
 */
class SurveyQuestionFactory extends Factory
{
    protected $model = SurveyQuestion::class;

    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'sequence' => 1,
            'question_type' => 'scale',
            'prompt' => 'How satisfied are you overall?',
            'options' => null,
            'is_required' => true,
            'skip_logic' => null,
        ];
    }
}
