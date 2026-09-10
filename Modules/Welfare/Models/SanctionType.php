<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Welfare\Database\Factories\SanctionTypeFactory;

/**
 * Book G BRD-07 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property int $severity_level
 * @property int|null $approval_chain_id
 * @property bool $requires_guardian_meeting
 * @property bool $requires_committee
 * @property bool $removes_from_lessons
 * @property bool $removes_from_campus
 * @property int|null $max_duration_days
 * @property bool $appealable
 * @property int $appeal_window_days
 * @property bool $is_active
 */
class SanctionType extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SanctionTypeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'severity_level', 'approval_chain_id', 'requires_guardian_meeting',
        'requires_committee', 'removes_from_lessons', 'removes_from_campus', 'max_duration_days',
        'appealable', 'appeal_window_days', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_guardian_meeting' => 'boolean',
            'requires_committee' => 'boolean',
            'removes_from_lessons' => 'boolean',
            'removes_from_campus' => 'boolean',
            'appealable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SanctionTypeFactory::new();
    }
}
