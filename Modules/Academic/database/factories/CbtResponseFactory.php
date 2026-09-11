<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtResponse;
use Modules\Academic\Models\QuestionBankItem;

/**
 * @extends Factory<CbtResponse>
 */
class CbtResponseFactory extends Factory
{
    protected $model = CbtResponse::class;

    public function definition(): array
    {
        $attempt = CbtCandidateAttempt::factory()->create();

        return [
            'school_id' => $attempt->school_id,
            'attempt_id' => $attempt->id,
            'question_id' => QuestionBankItem::factory()->create(['school_id' => $attempt->school_id])->id,
            'response_value' => [0],
            'is_flagged_by_candidate' => false,
            'last_saved_at' => now(),
        ];
    }
}
