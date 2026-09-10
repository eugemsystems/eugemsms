<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Database\Factories\ProjectRubricFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book E ACA-06 §2/BR-ACA-06-007.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $name
 * @property int|null $subject_id
 * @property string $total_mark
 * @property bool $is_template
 * @property bool $is_active
 */
class ProjectRubric extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProjectRubricFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = ['school_id', 'name', 'subject_id', 'total_mark', 'is_template', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_template' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProjectRubricFactory::new();
    }

    /**
     * @return HasMany<RubricCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class, 'rubric_id')->orderBy('sort_order');
    }
}
