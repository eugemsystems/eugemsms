<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Casts\SecondaryEncrypted;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\CounsellingSessionFactory;

/**
 * Book G BRD-08 §2/BR-BRD-08-015/016 — separate from casework.
 * `session_notes` is `SecondaryEncrypted`, visible to the recording
 * counsellor and the safeguarding lead only (enforced by whichever
 * read path exists — this pass has no Http/API layer, matching every
 * other module in this codebase).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int $counsellor_staff_id
 * @property Carbon $session_at
 * @property int|null $duration_minutes
 * @property string $session_type
 * @property string|null $referral_source
 * @property string|null $presenting_theme
 * @property string|null $session_notes
 * @property bool $risk_indicators_present
 * @property int|null $escalated_to_case_id
 * @property Carbon|null $next_session_on
 * @property bool $attended
 */
class CounsellingSession extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CounsellingSessionFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'counsellor_staff_id', 'session_at', 'duration_minutes', 'session_type',
        'referral_source', 'presenting_theme', 'session_notes', 'risk_indicators_present',
        'escalated_to_case_id', 'next_session_on', 'attended',
    ];

    protected function casts(): array
    {
        return [
            'session_at' => 'datetime',
            'session_notes' => SecondaryEncrypted::class,
            'risk_indicators_present' => 'boolean',
            'next_session_on' => 'date',
            'attended' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CounsellingSessionFactory::new();
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
    public function counsellorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'counsellor_staff_id');
    }

    /**
     * @return BelongsTo<SafeguardingCase, $this>
     */
    public function escalatedToCase(): BelongsTo
    {
        return $this->belongsTo(SafeguardingCase::class, 'escalated_to_case_id');
    }
}
