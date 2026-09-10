<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\AssessmentMarkFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book D ACA-05 §2/§4/BR-ACA-05-006/007/008/009 ⭐. The current value.
 * Freely rewritten while the owning `Assessment` is still `draft`/
 * `open` (`EnterMarkAction`'s autosave — BR-ACA-05-006); once
 * `submitted`, only `AmendMarkAction` may change it, and only together
 * with a new `assessment_mark_versions` row — that discipline lives in
 * the action, not a model guard, since draft edits legitimately touch
 * the same columns without versioning.
 *
 * @property int $id
 * @property int $school_id
 * @property int $assessment_id
 * @property int $student_id
 * @property int $term_id
 * @property string|null $raw_mark
 * @property string|null $percent
 * @property string|null $grade
 * @property string|null $points
 * @property bool $is_absent
 * @property string|null $absence_reason
 * @property string|null $comment
 * @property int $version
 * @property int $entered_by
 * @property Carbon $entered_at
 */
class AssessmentMark extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentMarkFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'assessment_id', 'student_id', 'term_id', 'raw_mark', 'percent', 'grade',
        'points', 'is_absent', 'absence_reason', 'comment', 'version', 'entered_by', 'entered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_absent' => 'boolean',
            'entered_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssessmentMarkFactory::new();
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
