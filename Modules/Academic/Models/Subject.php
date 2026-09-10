<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\SubjectFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book D ACA-01 §2 — a learning area, e.g. 'Combined Science'.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $framework_id
 * @property int|null $subject_group_id
 * @property string $code
 * @property string $name
 * @property string $short_name
 * @property string|null $zimsec_subject_code
 * @property string $subject_type
 * @property bool $is_examinable
 * @property bool $requires_sbp
 * @property int|null $sort_order
 * @property bool $is_active
 * @property string|null $coursework_weight_percent
 * @property int|null $grading_scale_id
 */
class Subject extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'framework_id', 'subject_group_id', 'code', 'name', 'short_name',
        'zimsec_subject_code', 'subject_type', 'is_examinable', 'requires_sbp',
        'sort_order', 'is_active', 'created_by', 'updated_by',
        'coursework_weight_percent', 'grading_scale_id',
    ];

    protected function casts(): array
    {
        return [
            'is_examinable' => 'boolean',
            'requires_sbp' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'coursework_weight_percent' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubjectFactory::new();
    }

    /**
     * @return BelongsTo<CurriculumFramework, $this>
     */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(CurriculumFramework::class, 'framework_id');
    }

    /**
     * @return BelongsTo<SubjectGroup, $this>
     */
    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class);
    }

    /**
     * @return BelongsTo<GradingScale, $this>
     */
    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }
}
