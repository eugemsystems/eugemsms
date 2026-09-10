<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\People\Database\Factories\IntakeFactory;

/**
 * Book C PPL-02 §2 — one admissions cycle for one grade level.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $name
 * @property int $grade_level_id
 * @property Carbon $opens_on
 * @property Carbon $closes_on
 * @property int $target_places
 * @property int $places_offered
 * @property int $places_accepted
 * @property int|null $application_fee_minor
 * @property string|null $application_fee_currency
 * @property int|null $acceptance_deposit_minor
 * @property string|null $acceptance_deposit_currency
 * @property int $deposit_deadline_days
 * @property bool $requires_entrance_exam
 * @property bool $requires_interview
 * @property string $status
 * @property bool $public_form_enabled
 */
class Intake extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<IntakeFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'name', 'grade_level_id', 'opens_on', 'closes_on',
        'target_places', 'places_offered', 'places_accepted', 'application_fee_minor',
        'application_fee_currency', 'acceptance_deposit_minor', 'acceptance_deposit_currency',
        'deposit_deadline_days', 'requires_entrance_exam', 'requires_interview', 'status',
        'public_form_enabled', 'public_form_slug', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opens_on' => 'date',
            'closes_on' => 'date',
            'target_places' => 'integer',
            'places_offered' => 'integer',
            'places_accepted' => 'integer',
            'deposit_deadline_days' => 'integer',
            'requires_entrance_exam' => 'boolean',
            'requires_interview' => 'boolean',
            'public_form_enabled' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return IntakeFactory::new();
    }

    public function hasCapacity(): bool
    {
        return $this->places_accepted < $this->target_places;
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
