<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Database\Factories\StudentGuardianFactory;

/**
 * Book C PPL-03 §3 ⭐/BR-PPL-03-005. The relationship AND its rights —
 * every right is granted independently here, never inferred from
 * `relationship`. `has_court_restriction` overrides `may_collect_learner`
 * regardless of its own value (BR-PPL-03-011) — enforced by whoever
 * reads this row (the gate terminal, once built), not by a DB
 * constraint, since the restriction must still be visible/auditable
 * rather than silently zeroing the flag out.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int $guardian_id
 * @property string $relationship
 * @property bool $is_primary_contact
 * @property bool $is_emergency_contact
 * @property bool $is_fee_responsible
 * @property bool $may_collect_learner
 * @property bool $may_authorise_exeat
 * @property bool $may_authorise_medical
 * @property bool $may_view_full_balance
 * @property bool $has_court_restriction
 * @property string $status
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class StudentGuardian extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentGuardianFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'student_guardian';

    protected $fillable = [
        'school_id', 'student_id', 'guardian_id', 'relationship', 'is_primary_contact',
        'is_emergency_contact', 'is_fee_responsible', 'may_collect_learner', 'may_authorise_exeat',
        'may_authorise_medical', 'may_view_full_balance', 'has_court_restriction', 'status', 'effective_from',
        'effective_to', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
            'is_emergency_contact' => 'boolean',
            'is_fee_responsible' => 'boolean',
            'may_collect_learner' => 'boolean',
            'may_authorise_exeat' => 'boolean',
            'may_authorise_medical' => 'boolean',
            'may_view_full_balance' => 'boolean',
            'has_court_restriction' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentGuardianFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * BR-PPL-03-011: the restriction always wins, regardless of any
     * other flag.
     */
    public function canCollectLearner(): bool
    {
        return $this->may_collect_learner && ! $this->has_court_restriction;
    }
}
