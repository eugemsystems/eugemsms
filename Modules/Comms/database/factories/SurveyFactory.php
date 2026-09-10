<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\Survey;
use Modules\Core\Models\School;

/**
 * @extends Factory<Survey>
 */
class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => 'Term Satisfaction Survey',
            'purpose' => 'satisfaction',
            'audience_scope' => 'whole_school',
            'is_anonymous' => false,
            'opens_at' => null,
            'closes_at' => null,
            'status' => 'open',
            'response_count' => 0,
        ];
    }

    public function anonymous(): self
    {
        return $this->state(fn (): array => ['is_anonymous' => true]);
    }
}
