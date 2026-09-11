<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\DepartmentMeetingFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;

/**
 * Book K ACA-11 §2/BR-ACA-11-008. Each `action_items` entry is
 * `{action, owner, due_date, status}` — see
 * `UpdateActionItemStatusAction` for the independently-trackable
 * update path this migration's docblock refers to.
 *
 * @property int $id
 * @property int $school_id
 * @property int $department_id
 * @property Carbon $meeting_date
 * @property array<int, int> $attendee_staff_ids
 * @property string|null $agenda
 * @property string $minutes
 * @property array<int, array<string, mixed>>|null $action_items
 * @property int $chaired_by
 */
class DepartmentMeeting extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DepartmentMeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'department_id', 'meeting_date', 'attendee_staff_ids', 'agenda', 'minutes',
        'action_items', 'chaired_by',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'attendee_staff_ids' => 'array',
            'action_items' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DepartmentMeetingFactory::new();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function chairedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'chaired_by');
    }
}
