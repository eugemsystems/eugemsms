<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Comms\Database\Factories\SurveyResponseAnswerFactory;

/**
 * Book I COM-08 §2.
 *
 * @property int $id
 * @property int $response_id
 * @property int $question_id
 * @property mixed $answer_value
 */
class SurveyResponseAnswer extends Model
{
    /** @use HasFactory<SurveyResponseAnswerFactory> */
    use HasFactory;

    protected $fillable = [
        'response_id', 'question_id', 'answer_value',
    ];

    protected function casts(): array
    {
        return [
            'answer_value' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SurveyResponseAnswerFactory::new();
    }

    /**
     * @return BelongsTo<SurveyResponse, $this>
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'response_id');
    }

    /**
     * @return BelongsTo<SurveyQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class);
    }
}
