<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Academic\Database\Factories\AttendanceReasonCodeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book D ACA-04 §2/BR-ACA-04-004/005/007.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property bool $counts_as_present
 * @property bool $counts_toward_percentage
 * @property bool $is_authorised
 * @property bool $requires_document
 * @property bool $suppresses_notification
 * @property bool $triggers_welfare_flag
 * @property int|null $sort_order
 * @property bool $is_active
 */
class AttendanceReasonCode extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AttendanceReasonCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'code', 'name', 'counts_as_present', 'counts_toward_percentage',
        'is_authorised', 'requires_document', 'suppresses_notification', 'triggers_welfare_flag',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'counts_as_present' => 'boolean',
            'counts_toward_percentage' => 'boolean',
            'is_authorised' => 'boolean',
            'requires_document' => 'boolean',
            'suppresses_notification' => 'boolean',
            'triggers_welfare_flag' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AttendanceReasonCodeFactory::new();
    }
}
