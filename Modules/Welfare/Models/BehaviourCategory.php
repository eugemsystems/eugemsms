<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Welfare\Database\Factories\BehaviourCategoryFactory;

/**
 * Book G BRD-07 §2/§3 ⭐ — `is_safeguarding_trigger` routes to `BRD-08`
 * and pauses discipline (BR-BRD-07-018).
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $polarity
 * @property int $default_points
 * @property int|null $severity_level
 * @property bool $requires_evidence
 * @property bool $requires_head_review
 * @property bool $auto_notify_guardian
 * @property int|null $suggests_sanction_id
 * @property bool $is_safeguarding_trigger
 * @property int $sort_order
 * @property bool $is_active
 */
class BehaviourCategory extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BehaviourCategoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'polarity', 'default_points', 'severity_level', 'requires_evidence',
        'requires_head_review', 'auto_notify_guardian', 'suggests_sanction_id', 'is_safeguarding_trigger',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_evidence' => 'boolean',
            'requires_head_review' => 'boolean',
            'auto_notify_guardian' => 'boolean',
            'is_safeguarding_trigger' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BehaviourCategoryFactory::new();
    }

    /**
     * @return BelongsTo<SanctionType, $this>
     */
    public function suggestedSanction(): BelongsTo
    {
        return $this->belongsTo(SanctionType::class, 'suggests_sanction_id');
    }
}
