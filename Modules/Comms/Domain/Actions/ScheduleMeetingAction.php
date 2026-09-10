<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use InvalidArgumentException;
use Modules\Comms\Domain\DataObjects\CreateProviderMeetingData;
use Modules\Comms\Domain\DataObjects\ScheduleMeetingData;
use Modules\Comms\Domain\Registry\MeetingProviderDriverRegistry;
use Modules\Comms\Models\MeetingProvider;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ScheduleMeeting (Book I COM-07 §2/3 ⭐/BR-COM-07-001/002/003).
 * `waiting_room_enabled` starts `true` unconditionally for a
 * learner-facing (`online_lesson`) meeting — `locked` per
 * `meetings.waiting_room_default_for_learners` — regardless of what
 * the caller passes; only `Modules\Comms\Domain\Actions\DisableWaitingRoomOverrideAction`
 * may ever turn it off (BR-COM-07-003). An `online_lesson` meeting
 * MUST carry a `timetableSlotId` — its mere existence for that slot
 * IS the "flagged for virtual delivery" marker (see the owning
 * migration's docblock); this action is the ONE place that
 * relationship is created, so it is always bidirectional by
 * construction.
 */
final class ScheduleMeetingAction extends Action
{
    private const array LEARNER_FACING_TYPES = ['online_lesson'];

    public function execute(ScheduleMeetingData $data): ScheduledMeeting
    {
        if (in_array($data->meetingType, self::LEARNER_FACING_TYPES, true) && $data->timetableSlotId === null) {
            throw new InvalidArgumentException('An online_lesson meeting requires a timetableSlotId — its presence is the "flagged for virtual delivery" marker.');
        }

        $isLearnerFacing = in_array($data->meetingType, self::LEARNER_FACING_TYPES, true);
        $provider = MeetingProvider::findOrFail($data->providerId);
        $driver = MeetingProviderDriverRegistry::forProvider($provider->provider);

        $created = $driver?->createMeeting(new CreateProviderMeetingData(
            topic: $data->topic,
            startsAt: $data->startsAt,
            durationMinutes: $data->durationMinutes,
            waitingRoomEnabled: $isLearnerFacing || $data->waitingRoomEnabled,
        ));

        return $this->transaction(fn (): ScheduledMeeting => ScheduledMeeting::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'meeting_type' => $data->meetingType,
            'provider_id' => $data->providerId,
            'timetable_slot_id' => $data->timetableSlotId,
            'provider_meeting_id' => $created?->providerMeetingId,
            'join_url' => $created?->joinUrl,
            'host_url' => $created?->hostUrl,
            'passcode' => $created?->passcode,
            'starts_at' => $data->startsAt,
            'duration_minutes' => $data->durationMinutes,
            'host_staff_id' => $data->hostStaffId,
            'waiting_room_enabled' => $isLearnerFacing || $data->waitingRoomEnabled,
            'recording_enabled' => $data->recordingEnabled,
            'status' => 'scheduled',
        ]));
    }
}
