<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Database\Factories\PeriodStructureFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\SchoolSection;

/**
 * Book E ACA-03 §2/BR-ACA-03-001 — the shape of a school day, per
 * section per academic year.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $section_id
 * @property int $academic_year_id
 * @property string $name
 * @property string $cycle_type
 * @property int $cycle_days
 * @property array<int, string> $day_labels
 * @property bool $is_default
 * @property bool $is_active
 */
class PeriodStructure extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PeriodStructureFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'section_id', 'academic_year_id', 'name', 'cycle_type', 'cycle_days',
        'day_labels', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_labels' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PeriodStructureFactory::new();
    }

    /**
     * @return BelongsTo<SchoolSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return HasMany<PeriodSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(PeriodSlot::class, 'structure_id')->orderBy('cycle_day')->orderBy('period_number');
    }
}
