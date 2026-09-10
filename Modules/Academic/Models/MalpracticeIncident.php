<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\MalpracticeIncidentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book E ACA-07 §2/BR-ACA-07-018/019. `is_confidential` marks the row
 * for tiered visibility enforced at the Livewire/API layer.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $session_id
 * @property int|null $paper_id
 * @property int|null $candidate_id
 * @property string $incident_type
 * @property string $description
 * @property array<int, int>|null $evidence_file_ids
 * @property int $reported_by
 * @property Carbon $occurred_at
 * @property string|null $investigation_notes
 * @property string|null $outcome
 * @property int|null $outcome_by
 * @property string $status
 * @property bool $is_confidential
 */
class MalpracticeIncident extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MalpracticeIncidentFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'session_id', 'paper_id', 'candidate_id', 'incident_type', 'description',
        'evidence_file_ids', 'reported_by', 'occurred_at', 'investigation_notes', 'outcome',
        'outcome_by', 'status', 'is_confidential',
    ];

    protected function casts(): array
    {
        return [
            'evidence_file_ids' => 'array',
            'occurred_at' => 'datetime',
            'is_confidential' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MalpracticeIncidentFactory::new();
    }

    /**
     * @return BelongsTo<ExaminationSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExaminationSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<ExaminationPaper, $this>
     */
    public function paper(): BelongsTo
    {
        return $this->belongsTo(ExaminationPaper::class, 'paper_id');
    }

    /**
     * @return BelongsTo<ExaminationCandidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ExaminationCandidate::class, 'candidate_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function outcomeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outcome_by');
    }
}
