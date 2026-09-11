<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Finance\Database\Factories\ScholarshipApplicationFactory;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book K FIN-07 §2/BR-FIN-07-005/006.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $scheme_id
 * @property int $student_id
 * @property int|null $applied_by_guardian_id
 * @property string|null $household_income_band
 * @property array<int, int>|null $supporting_document_ids
 * @property string|null $means_assessment_score
 * @property string|null $academic_average_at_application
 * @property string|null $narrative
 * @property string $status
 * @property string|null $committee_notes
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $rejection_reason
 */
class ScholarshipApplication extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ScholarshipApplicationFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'scheme_id', 'student_id', 'applied_by_guardian_id',
        'household_income_band', 'supporting_document_ids', 'means_assessment_score',
        'academic_average_at_application', 'narrative', 'status', 'committee_notes',
        'decided_by', 'decided_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'supporting_document_ids' => 'array',
            'means_assessment_score' => 'decimal:2',
            'academic_average_at_application' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ScholarshipApplicationFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<DiscountScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(DiscountScheme::class, 'scheme_id');
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
    public function appliedByGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'applied_by_guardian_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
