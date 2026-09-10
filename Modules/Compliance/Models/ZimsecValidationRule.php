<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Compliance\Database\Factories\ZimsecValidationRuleFactory;

/**
 * Book H3 CMP-01 §2/BR-CMP-01-002/003 ⭐. `school_id` null means a
 * system-wide default rule; `exam_level` null means it applies to
 * every level. `ValidateZimsecCandidatesAction` is the only code that
 * reads this table.
 *
 * @property int $id
 * @property int|null $school_id
 * @property string|null $exam_level
 * @property string $field
 * @property string $rule_type
 * @property string|null $rule_value
 * @property string $severity
 * @property string $message
 * @property bool $is_active
 */
class ZimsecValidationRule extends Model
{
    /** @use HasFactory<ZimsecValidationRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'exam_level', 'field', 'rule_type', 'rule_value', 'severity', 'message', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ZimsecValidationRuleFactory::new();
    }
}
