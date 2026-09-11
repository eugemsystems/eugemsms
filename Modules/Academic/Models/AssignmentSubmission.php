<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\AssignmentSubmissionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Student;

/**
 * Book K ACA-08 §2/BR-ACA-08-005/006/007. Each `attempt_number` is a
 * new, retained row (BR-ACA-08-007) — never overwritten.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $assignment_id
 * @property int $student_id
 * @property int $attempt_number
 * @property string|null $submitted_text
 * @property string|null $submitted_link
 * @property array<int, int>|null $file_ids
 * @property Carbon|null $submitted_at
 * @property bool $is_late
 * @property int|null $minutes_late
 * @property bool $similarity_flag
 * @property array<int, int>|null $similarity_matches
 * @property string|null $raw_mark
 * @property string|null $penalty_applied_percent
 * @property string|null $final_mark
 * @property string|null $feedback
 * @property int|null $marked_by
 * @property Carbon|null $marked_at
 * @property string $status
 */
class AssignmentSubmission extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssignmentSubmissionFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'assignment_id', 'student_id', 'attempt_number', 'submitted_text',
        'submitted_link', 'file_ids', 'submitted_at', 'is_late', 'minutes_late', 'similarity_flag',
        'similarity_matches', 'raw_mark', 'penalty_applied_percent', 'final_mark', 'feedback',
        'marked_by', 'marked_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'file_ids' => 'array',
            'submitted_at' => 'datetime',
            'is_late' => 'boolean',
            'similarity_flag' => 'boolean',
            'similarity_matches' => 'array',
            'marked_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssignmentSubmissionFactory::new();
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
