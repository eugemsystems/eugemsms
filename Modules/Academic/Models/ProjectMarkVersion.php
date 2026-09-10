<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ProjectMarkVersionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book E ACA-06 §5 — append-only mark history for one learner
 * project, mirroring `AssessmentMarkVersion` (Book D ACA-05).
 * Corrections are new versions, never updates to an old one.
 *
 * @property int $id
 * @property int $school_id
 * @property int $learner_project_id
 * @property int $version
 * @property string|null $raw_mark
 * @property array<string, mixed>|null $criterion_marks
 * @property string $stage
 * @property string|null $change_reason
 * @property int $changed_by
 * @property Carbon $changed_at
 */
class ProjectMarkVersion extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProjectMarkVersionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'learner_project_id', 'version', 'raw_mark', 'criterion_marks', 'stage', 'change_reason', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return [
            'criterion_marks' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProjectMarkVersionFactory::new();
    }

    /**
     * @return BelongsTo<LearnerProject, $this>
     */
    public function learnerProject(): BelongsTo
    {
        return $this->belongsTo(LearnerProject::class, 'learner_project_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
