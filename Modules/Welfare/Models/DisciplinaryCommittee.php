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
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\DisciplinaryCommitteeFactory;

/**
 * Book G BRD-07 §2/BR-BRD-07-006.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property Carbon $convened_on
 * @property array<int, int> $panel_staff_ids
 * @property bool|null $guardian_present
 * @property bool|null $learner_present
 * @property string|null $learner_statement
 * @property string|null $guardian_statement
 * @property array<int, mixed>|null $evidence_reviewed
 * @property string $findings
 * @property string $decision
 * @property int|null $recommended_sanction_id
 * @property int|null $minutes_document_id
 * @property int $chaired_by
 */
class DisciplinaryCommittee extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DisciplinaryCommitteeFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'convened_on', 'panel_staff_ids', 'guardian_present', 'learner_present',
        'learner_statement', 'guardian_statement', 'evidence_reviewed', 'findings', 'decision',
        'recommended_sanction_id', 'minutes_document_id', 'chaired_by',
    ];

    protected function casts(): array
    {
        return [
            'convened_on' => 'date',
            'panel_staff_ids' => 'array',
            'guardian_present' => 'boolean',
            'learner_present' => 'boolean',
            'evidence_reviewed' => 'array',
        ];
    }

    /**
     * BR-BRD-07-006 — a valid committee record either has the
     * learner's own statement, or an explicit note that they declined.
     */
    public function hasLearnerAccount(): bool
    {
        return $this->learner_statement !== null;
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DisciplinaryCommitteeFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Sanction, $this>
     */
    public function recommendedSanction(): BelongsTo
    {
        return $this->belongsTo(Sanction::class, 'recommended_sanction_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function chairedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chaired_by');
    }
}
