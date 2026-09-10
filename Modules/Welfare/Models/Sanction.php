<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\SanctionFactory;

/**
 * Book G BRD-07 §2/BR-BRD-07-004/005/007/008/009/010 ⭐. An overturned
 * sanction is marked `overturned`, never deleted (BR-BRD-07-009).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $sanction_type_id
 * @property array<int, int> $behaviour_record_ids
 * @property string $reason
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $duration_days
 * @property string $status
 * @property int|null $approval_request_id
 * @property int $issued_by
 * @property int|null $approved_by
 * @property Carbon|null $guardian_notified_at
 * @property Carbon|null $guardian_meeting_at
 * @property string|null $guardian_meeting_notes
 * @property int|null $committee_record_id
 * @property string|null $conditions
 * @property string|null $boarding_arrangements
 * @property Carbon|null $completed_at
 * @property string|null $completion_notes
 * @property int|null $document_id
 */
class Sanction extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SanctionFactory> */
    use HasFactory;

    use HasUlid;

    public const array ACTIVE_STATUSES = ['active'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'sanction_type_id', 'behaviour_record_ids',
        'reason', 'starts_on', 'ends_on', 'duration_days', 'status', 'approval_request_id', 'issued_by',
        'approved_by', 'guardian_notified_at', 'guardian_meeting_at', 'guardian_meeting_notes',
        'committee_record_id', 'conditions', 'boarding_arrangements', 'completed_at', 'completion_notes', 'document_id',
    ];

    protected function casts(): array
    {
        return [
            'behaviour_record_ids' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'guardian_notified_at' => 'datetime',
            'guardian_meeting_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SanctionFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<SanctionType, $this>
     */
    public function sanctionType(): BelongsTo
    {
        return $this->belongsTo(SanctionType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
