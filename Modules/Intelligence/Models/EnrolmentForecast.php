<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Intelligence\Database\Factories\EnrolmentForecastFactory;

/**
 * Book J INT-03 §2/BR-INT-03-007.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $grade_level_id
 * @property int|null $projected_intake
 * @property int|null $projected_attrition
 * @property string $confidence_band
 * @property string $basis_note
 * @property Carbon $computed_at
 */
class EnrolmentForecast extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EnrolmentForecastFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'grade_level_id', 'projected_intake',
        'projected_attrition', 'confidence_band', 'basis_note', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EnrolmentForecastFactory::new();
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
}
