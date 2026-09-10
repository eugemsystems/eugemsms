<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\AssessmentInstrumentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book E ACA-06 §2/§3 ⭐/BR-ACA-06-001.
 *
 * @property int $id
 * @property int $school_id
 * @property int $framework_id
 * @property string $code
 * @property string $name
 * @property int $projects_per_subject_per_year
 * @property bool $applies_to_exam_classes
 * @property bool $contributes_to_final_mark
 * @property string|null $default_weight_percent
 * @property bool $is_readonly
 * @property string|null $reference_circular
 * @property string $status
 */
class AssessmentInstrument extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentInstrumentFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'framework_id', 'code', 'name', 'projects_per_subject_per_year',
        'applies_to_exam_classes', 'contributes_to_final_mark', 'default_weight_percent',
        'is_readonly', 'reference_circular', 'status',
    ];

    protected function casts(): array
    {
        return [
            'applies_to_exam_classes' => 'boolean',
            'contributes_to_final_mark' => 'boolean',
            'is_readonly' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssessmentInstrumentFactory::new();
    }

    /**
     * @return BelongsTo<CurriculumFramework, $this>
     */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(CurriculumFramework::class, 'framework_id');
    }
}
