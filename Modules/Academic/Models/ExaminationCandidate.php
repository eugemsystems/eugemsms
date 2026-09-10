<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ExaminationCandidateFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Student;

/**
 * Book E ACA-07 §2/BR-ACA-07-001/002.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $session_id
 * @property int $student_id
 * @property string $index_number
 * @property string $entry_status
 * @property array<int, int> $entered_subjects
 * @property int|null $entry_fee_minor
 * @property string|null $entry_fee_currency
 * @property bool $entry_invoiced
 * @property int|null $statement_of_entry_doc_id
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 */
class ExaminationCandidate extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExaminationCandidateFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'session_id', 'student_id', 'index_number', 'entry_status', 'entered_subjects',
        'entry_fee_minor', 'entry_fee_currency', 'entry_invoiced', 'statement_of_entry_doc_id',
        'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'entered_subjects' => 'array',
            'entry_invoiced' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExaminationCandidateFactory::new();
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
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
