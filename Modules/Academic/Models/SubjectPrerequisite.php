<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\SubjectPrerequisiteFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book D ACA-01 §2/BR-ACA-01-012. Validates only against internal
 * enrolment history in this pass — `student_prior_results` (external
 * results captured at admission) doesn't exist yet, and neither does
 * `ACA-05`'s own results, so `minimum_grade`/`examination` are
 * captured but not yet enforced; `SubjectPrerequisiteChecker` only
 * checks whether the learner has ever been enrolled in the
 * prerequisite subject at all.
 *
 * @property int $id
 * @property int $school_id
 * @property int $subject_id
 * @property int $prerequisite_subject_id
 * @property string|null $minimum_grade
 * @property string|null $examination
 * @property string $severity
 */
class SubjectPrerequisite extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SubjectPrerequisiteFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'subject_id', 'prerequisite_subject_id', 'minimum_grade', 'examination', 'severity',
    ];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubjectPrerequisiteFactory::new();
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function prerequisiteSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'prerequisite_subject_id');
    }
}
