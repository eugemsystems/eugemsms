<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Models\ExaminationSession;
use Modules\Compliance\Database\Factories\ZimsecRegistrationFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;

/**
 * Book H3 CMP-01 §2. One row per exam level/series a school is
 * registering candidates for.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int|null $examination_session_id
 * @property string $exam_level
 * @property string $exam_series
 * @property string $centre_number
 * @property Carbon|null $registration_opens_on
 * @property Carbon $registration_closes_on
 * @property int $candidate_count
 * @property int $validated_count
 * @property int $error_count
 * @property int $total_fees_minor
 * @property int $collected_minor
 * @property int $remitted_minor
 * @property string $currency
 * @property int|null $export_file_id
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property string|null $zimsec_reference
 */
class ZimsecRegistration extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ZimsecRegistrationFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'examination_session_id', 'exam_level', 'exam_series',
        'centre_number', 'registration_opens_on', 'registration_closes_on', 'candidate_count',
        'validated_count', 'error_count', 'total_fees_minor', 'collected_minor', 'remitted_minor',
        'currency', 'export_file_id', 'status', 'submitted_at', 'submitted_by', 'zimsec_reference',
    ];

    protected function casts(): array
    {
        return [
            'registration_opens_on' => 'date',
            'registration_closes_on' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ZimsecRegistrationFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<ExaminationSession, $this>
     */
    public function examinationSession(): BelongsTo
    {
        return $this->belongsTo(ExaminationSession::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return HasMany<ZimsecCandidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(ZimsecCandidate::class, 'registration_id');
    }

    /**
     * @return HasMany<ZimsecResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(ZimsecResult::class, 'registration_id');
    }
}
