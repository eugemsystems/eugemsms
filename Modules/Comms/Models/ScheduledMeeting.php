<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\ScheduledMeetingFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;

/**
 * Book I COM-07 §2 ⭐/BR-COM-07-001.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $meeting_type
 * @property int $provider_id
 * @property int|null $timetable_slot_id
 * @property string|null $provider_meeting_id
 * @property string|null $join_url
 * @property string|null $host_url
 * @property string|null $passcode
 * @property Carbon $starts_at
 * @property int $duration_minutes
 * @property int|null $host_staff_id
 * @property bool $waiting_room_enabled
 * @property bool $recording_enabled
 * @property string|null $recording_url
 * @property Carbon|null $recording_expires_on
 * @property string $status
 */
class ScheduledMeeting extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ScheduledMeetingFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'term_id', 'meeting_type', 'provider_id', 'timetable_slot_id',
        'provider_meeting_id', 'join_url', 'host_url', 'passcode', 'starts_at',
        'duration_minutes', 'host_staff_id', 'waiting_room_enabled', 'recording_enabled',
        'recording_url', 'recording_expires_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'host_url' => 'encrypted',
            'passcode' => 'encrypted',
            'starts_at' => 'datetime',
            'waiting_room_enabled' => 'boolean',
            'recording_enabled' => 'boolean',
            'recording_expires_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ScheduledMeetingFactory::new();
    }

    /**
     * @return BelongsTo<MeetingProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(MeetingProvider::class, 'provider_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function hostStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'host_staff_id');
    }

    public function isLearnerFacing(): bool
    {
        return $this->meeting_type === 'online_lesson';
    }
}
