<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\LearnerProjectMilestoneFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book E ACA-06 §2 — one learner's submission against one milestone.
 *
 * @property int $id
 * @property int $school_id
 * @property int $learner_project_id
 * @property int $milestone_id
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property string|null $mark
 * @property string|null $feedback
 * @property int|null $reviewed_by
 */
class LearnerProjectMilestone extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LearnerProjectMilestoneFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'learner_project_id', 'milestone_id', 'status', 'submitted_at', 'mark', 'feedback', 'reviewed_by'];

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
        return LearnerProjectMilestoneFactory::new();
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
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
