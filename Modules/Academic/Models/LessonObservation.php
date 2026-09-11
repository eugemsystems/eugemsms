<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\LessonObservationFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book K ACA-11 §2/BR-ACA-11-005/006 — see the owning migration's
 * docblock for why `teacher_acknowledged`/`teacher_comments` are the
 * only fields the observed teacher may ever write.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $observed_staff_id
 * @property int $observer_staff_id
 * @property int $rubric_id
 * @property Carbon $observed_at
 * @property string|null $class_observed
 * @property int|null $subject_id
 * @property array<string, string> $scores
 * @property string|null $strengths_noted
 * @property string|null $areas_for_development
 * @property string|null $overall_rating
 * @property bool $teacher_acknowledged
 * @property string|null $teacher_comments
 * @property int|null $follow_up_observation_id
 */
class LessonObservation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LessonObservationFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'term_id', 'observed_staff_id', 'observer_staff_id', 'rubric_id', 'observed_at',
        'class_observed', 'subject_id', 'scores', 'strengths_noted', 'areas_for_development', 'overall_rating',
        'teacher_acknowledged', 'teacher_comments', 'follow_up_observation_id',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'scores' => 'array',
            'teacher_acknowledged' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LessonObservationFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function observedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'observed_staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function observerStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'observer_staff_id');
    }

    /**
     * @return BelongsTo<ObservationRubric, $this>
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(ObservationRubric::class, 'rubric_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function followUpOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'follow_up_observation_id');
    }
}
