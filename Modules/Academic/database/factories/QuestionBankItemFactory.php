<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\School;

/**
 * @extends Factory<QuestionBankItem>
 */
class QuestionBankItemFactory extends Factory
{
    protected $model = QuestionBankItem::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'subject_id' => Subject::factory()->for($school),
            'topic' => 'Mechanics',
            'item_type' => 'mcq',
            'difficulty' => 'medium',
            'prompt' => 'What is the SI unit of force?',
            'options' => ['Newton', 'Joule', 'Watt', 'Pascal'],
            'correct_answer' => [0],
            'max_mark' => 1,
            'is_auto_markable' => true,
            'usage_count' => 0,
            'created_by' => User::factory(),
            'is_active' => true,
        ];
    }

    public function essay(): self
    {
        return $this->state(fn (): array => [
            'item_type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'max_mark' => 10,
            'is_auto_markable' => false,
        ]);
    }
}
