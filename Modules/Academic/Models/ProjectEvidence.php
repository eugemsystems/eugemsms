<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ProjectEvidenceFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book E ACA-06 §2 — one piece of milestone or final-submission
 * evidence. `file_id` is a forward reference to CORE-10 (no FK yet).
 *
 * @property int $id
 * @property int $school_id
 * @property int $learner_project_id
 * @property int|null $milestone_id
 * @property string $evidence_type
 * @property int|null $file_id
 * @property string|null $external_url
 * @property string|null $caption
 * @property int $uploaded_by
 * @property Carbon $uploaded_at
 * @property bool $is_final_submission
 */
class ProjectEvidence extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProjectEvidenceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'learner_project_id', 'milestone_id', 'evidence_type', 'file_id',
        'external_url', 'caption', 'uploaded_by', 'uploaded_at', 'is_final_submission',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'is_final_submission' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProjectEvidenceFactory::new();
    }

    /**
     * @return BelongsTo<LearnerProject, $this>
     */
    public function learnerProject(): BelongsTo
    {
        return $this->belongsTo(LearnerProject::class, 'learner_project_id');
    }

    /**
     * @return BelongsTo<ProjectMilestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
