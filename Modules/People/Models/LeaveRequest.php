<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Database\Factories\LeaveRequestFactory;

/**
 * Book C PPL-04 §2/§4.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $staff_id
 * @property int $leave_type_id
 * @property int $academic_year_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string $working_days
 * @property string|null $reason
 * @property int|null $cover_staff_id
 * @property string $status
 * @property string|null $contact_while_away
 */
class LeaveRequest extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'staff_id', 'leave_type_id', 'academic_year_id', 'starts_on', 'ends_on',
        'working_days', 'reason', 'supporting_document_id', 'cover_staff_id', 'status',
        'approval_request_id', 'contact_while_away', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'working_days' => 'decimal:1',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LeaveRequestFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function coverStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'cover_staff_id');
    }
}
