<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\LevelSubjectOfferingFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\GradeLevel;

/**
 * Book D ACA-01 §2/BR-ACA-01-006/010/011 — which subjects exist at
 * which level, per academic year.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $grade_level_id
 * @property int $subject_id
 * @property int|null $pathway_id
 * @property bool $is_compulsory
 * @property bool $is_available
 * @property int|null $periods_per_week
 * @property int|null $max_learners
 * @property string|null $option_block
 * @property int|null $sort_order
 */
class LevelSubjectOffering extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LevelSubjectOfferingFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'grade_level_id', 'subject_id', 'pathway_id',
        'is_compulsory', 'is_available', 'periods_per_week', 'max_learners', 'option_block', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_compulsory' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LevelSubjectOfferingFactory::new();
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Pathway, $this>
     */
    public function pathway(): BelongsTo
    {
        return $this->belongsTo(Pathway::class);
    }
}
