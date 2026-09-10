<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\ExaminationMarkFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book E ACA-07 §2/BR-ACA-07-013/014/016. No `ulid` — always reached
 * through its paper and candidate.
 *
 * @property int $id
 * @property int $school_id
 * @property int $paper_id
 * @property int $candidate_id
 * @property int $student_id
 * @property string|null $raw_mark
 * @property string|null $percent
 * @property bool $is_absent
 * @property int|null $first_marker_id
 * @property string|null $first_mark
 * @property int|null $second_marker_id
 * @property string|null $second_mark
 * @property string|null $mark_variance
 * @property int|null $final_marker_id
 * @property string|null $moderated_mark
 * @property int|null $moderator_id
 * @property string $status
 * @property int $version
 */
class ExaminationMark extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExaminationMarkFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'paper_id', 'candidate_id', 'student_id', 'raw_mark', 'percent', 'is_absent',
        'first_marker_id', 'first_mark', 'second_marker_id', 'second_mark', 'mark_variance',
        'final_marker_id', 'moderated_mark', 'moderator_id', 'status', 'version',
    ];

    protected function casts(): array
    {
        return [
            'is_absent' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExaminationMarkFactory::new();
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
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function firstMarker(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'first_marker_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function secondMarker(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'second_marker_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'moderator_id');
    }
}
