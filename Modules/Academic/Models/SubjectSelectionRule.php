<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\SubjectSelectionRuleFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book D ACA-01 §3 ⭐/BR-ACA-01-007. One row per constraint —
 * `SubjectSelectionRuleEngine` is the only code that reads these.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $framework_id
 * @property int|null $grade_level_id
 * @property string|null $pathway
 * @property string $rule_type
 * @property int|null $subject_group_id
 * @property array<int, int>|null $subject_ids
 * @property int|null $min_count
 * @property int|null $max_count
 * @property string $severity
 * @property string $message
 * @property string|null $source_reference
 * @property bool $requires_confirmation
 * @property bool $is_active
 */
class SubjectSelectionRule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SubjectSelectionRuleFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'framework_id', 'grade_level_id', 'pathway', 'rule_type',
        'subject_group_id', 'subject_ids', 'min_count', 'max_count', 'severity',
        'message', 'source_reference', 'requires_confirmation', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'subject_ids' => 'array',
            'min_count' => 'integer',
            'max_count' => 'integer',
            'requires_confirmation' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubjectSelectionRuleFactory::new();
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
}
