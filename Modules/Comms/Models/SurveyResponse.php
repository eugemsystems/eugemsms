<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\SurveyResponseFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-001 (AC-COM-08-001).
 *
 * @property int $id
 * @property int $school_id
 * @property int $survey_id
 * @property string|null $respondent_type
 * @property int|null $respondent_id
 * @property Carbon $submitted_at
 */
class SurveyResponse extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SurveyResponseFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'survey_id', 'respondent_type', 'respondent_id', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SurveyResponseFactory::new();
    }

    /**
     * @return BelongsTo<Survey, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * @return HasMany<SurveyResponseAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SurveyResponseAnswer::class, 'response_id');
    }

    public function isAnonymous(): bool
    {
        return $this->respondent_type === null;
    }
}
