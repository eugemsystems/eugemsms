<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Comms\Database\Factories\SurveyQuestionFactory;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-002. `skip_logic` shape:
 * `{if_answer: mixed, go_to_sequence: int}` — see
 * `Modules\Comms\Domain\Support\SkipLogicEvaluator`.
 *
 * @property int $id
 * @property int $survey_id
 * @property int $sequence
 * @property string $question_type
 * @property string $prompt
 * @property array<int, mixed>|null $options
 * @property bool $is_required
 * @property array{if_answer: mixed, go_to_sequence: int}|null $skip_logic
 */
class SurveyQuestion extends Model
{
    /** @use HasFactory<SurveyQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'survey_id', 'sequence', 'question_type', 'prompt', 'options', 'is_required', 'skip_logic',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'skip_logic' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SurveyQuestionFactory::new();
    }

    /**
     * @return BelongsTo<Survey, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
