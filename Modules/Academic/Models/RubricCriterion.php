<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\RubricCriterionFactory;

/**
 * Book E ACA-06 §2/BR-ACA-06-007. No `school_id` of its own — always
 * reached through its owning `ProjectRubric`.
 *
 * @property int $id
 * @property int $rubric_id
 * @property string $criterion
 * @property string|null $description
 * @property string $max_mark
 * @property string $weight_percent
 * @property array<int, array<string, mixed>> $performance_levels
 * @property int|null $sort_order
 */
class RubricCriterion extends Model
{
    /** @use HasFactory<RubricCriterionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['rubric_id', 'criterion', 'description', 'max_mark', 'weight_percent', 'performance_levels', 'sort_order'];

    protected function casts(): array
    {
        return [
            'performance_levels' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RubricCriterionFactory::new();
    }

    /**
     * @return BelongsTo<ProjectRubric, $this>
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(ProjectRubric::class, 'rubric_id');
    }
}
