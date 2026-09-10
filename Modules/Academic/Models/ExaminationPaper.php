<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ExaminationPaperFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\GradeLevel;
use Modules\People\Models\Staff;

/**
 * Book E ACA-07 §2/§3 ⭐.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $session_id
 * @property int $subject_id
 * @property int $grade_level_id
 * @property string $paper_number
 * @property string $paper_name
 * @property string $component_type
 * @property string $max_mark
 * @property string $weight_percent
 * @property int $duration_minutes
 * @property Carbon|null $scheduled_date
 * @property string|null $scheduled_start
 * @property string|null $requires_special_venue
 * @property int|null $paper_file_id
 * @property int|null $marking_scheme_file_id
 * @property Carbon|null $release_at
 * @property Carbon|null $released_at
 * @property int|null $released_by
 * @property int|null $setter_staff_id
 * @property int|null $vetted_by
 * @property Carbon|null $vetted_at
 * @property string $status
 */
class ExaminationPaper extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExaminationPaperFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'session_id', 'subject_id', 'grade_level_id', 'paper_number', 'paper_name',
        'component_type', 'max_mark', 'weight_percent', 'duration_minutes', 'scheduled_date',
        'scheduled_start', 'requires_special_venue', 'paper_file_id', 'marking_scheme_file_id',
        'release_at', 'released_at', 'released_by', 'setter_staff_id', 'vetted_by', 'vetted_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'release_at' => 'datetime',
            'released_at' => 'datetime',
            'vetted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExaminationPaperFactory::new();
    }

    /**
     * @return BelongsTo<ExaminationSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExaminationSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function setterStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'setter_staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function vettedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'vetted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * @return HasMany<ScriptBatch, $this>
     */
    public function scriptBatches(): HasMany
    {
        return $this->hasMany(ScriptBatch::class, 'paper_id');
    }
}
