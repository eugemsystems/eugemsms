<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\SyllabusFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\GradeLevel;

/**
 * Book D ACA-01 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $subject_id
 * @property int|null $grade_level_id
 * @property int $framework_id
 * @property string $title
 * @property string|null $version
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $file_id
 * @property array<string, mixed>|null $topics
 * @property bool $is_active
 */
class Syllabus extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SyllabusFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'subject_id', 'grade_level_id', 'framework_id', 'title', 'version',
        'effective_from', 'effective_to', 'file_id', 'topics', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'topics' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SyllabusFactory::new();
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
     * @return BelongsTo<CurriculumFramework, $this>
     */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(CurriculumFramework::class, 'framework_id');
    }
}
