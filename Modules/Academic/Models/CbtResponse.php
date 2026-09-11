<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\CbtResponseFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book K ACA-09 §2/§3 ⭐/BR-ACA-09-001/007. One row per question per
 * attempt, upserted on every autosave.
 *
 * @property int $id
 * @property int $school_id
 * @property int $attempt_id
 * @property int $question_id
 * @property mixed $response_value
 * @property bool $is_flagged_by_candidate
 * @property bool|null $auto_mark_correct
 * @property string|null $mark_awarded
 * @property string|null $manual_feedback
 * @property int|null $marked_by
 * @property Carbon $last_saved_at
 */
class CbtResponse extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CbtResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'attempt_id', 'question_id', 'response_value', 'is_flagged_by_candidate',
        'auto_mark_correct', 'mark_awarded', 'manual_feedback', 'marked_by', 'last_saved_at',
    ];

    protected function casts(): array
    {
        return [
            'response_value' => 'array',
            'is_flagged_by_candidate' => 'boolean',
            'auto_mark_correct' => 'boolean',
            'last_saved_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CbtResponseFactory::new();
    }

    /**
     * @return BelongsTo<CbtCandidateAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CbtCandidateAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<QuestionBankItem, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBankItem::class, 'question_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function isMarked(): bool
    {
        return $this->mark_awarded !== null;
    }
}
