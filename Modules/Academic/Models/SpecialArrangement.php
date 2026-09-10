<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\SpecialArrangementFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;

/**
 * Book E ACA-07 §2/BR-ACA-07-009. No `ulid` — always reached through
 * its session/student.
 *
 * @property int $id
 * @property int $school_id
 * @property int $session_id
 * @property int $student_id
 * @property string $arrangement_type
 * @property int|null $extra_time_percent
 * @property string $justification
 * @property int|null $supporting_document_id
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property array<int, int>|null $applies_to_papers
 * @property string $status
 */
class SpecialArrangement extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SpecialArrangementFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'session_id', 'student_id', 'arrangement_type', 'extra_time_percent',
        'justification', 'supporting_document_id', 'approved_by', 'approved_at', 'applies_to_papers', 'status',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'applies_to_papers' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SpecialArrangementFactory::new();
    }

    /**
     * @return BelongsTo<ExaminationSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExaminationSession::class, 'session_id');
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
