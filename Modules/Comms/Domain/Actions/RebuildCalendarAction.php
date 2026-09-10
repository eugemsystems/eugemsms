<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\CalendarSourceRecord;
use Modules\Comms\Domain\Registry\CalendarSourceRegistry;
use Modules\Comms\Models\CalendarEvent;
use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Guardian;
use Throwable;

/**
 * ACT-RebuildCalendar (Book I COM-06 §2/3 ⭐/BR-COM-06-001/002/009).
 * The nightly (or on-demand) reconciliation every registered source
 * feeds. For each `CalendarSourceRegistry` entry: upsert every record
 * the source's own `syncer` currently returns (keyed on
 * `(school_id, source_type, source_module_id)`, matching the schema's
 * own unique constraint), then delete whatever this source previously
 * produced that it no longer returns — a moved or cancelled source
 * record (BR-COM-06-009) disappears from the aggregate the same way.
 * A stale event that has an `event_registrations` row is never
 * hard-deleted — `event_registrations.calendar_event_id` cascades on
 * delete, which would silently erase real ticketing/attendance
 * history (a paid `AdHocCharge` link included) along with it. Such an
 * event is left in place (its own `starts_at`/etc. simply stop being
 * refreshed) and every active attendee is notified and cancelled
 * instead — the same "notify, don't destroy" resolution `BR-COM-06-009`
 * asks for, applied honestly to a schema where hard deletion and
 * "preserve the paper trail" are in genuine tension.
 */
final class RebuildCalendarAction extends Action
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(int $schoolId): int
    {
        $rebuiltCount = 0;

        foreach (CalendarSourceRegistry::all() as $sourceType => $definition) {
            $records = ($definition->syncer)($schoolId);
            $seenModuleIds = [];

            foreach ($records as $record) {
                /** @var CalendarSourceRecord $record */
                $seenModuleIds[] = $record->sourceModuleId;

                $this->transaction(function () use ($schoolId, $sourceType, $definition, $record): void {
                    CalendarEvent::updateOrCreate(
                        ['school_id' => $schoolId, 'source_type' => $sourceType, 'source_module_id' => $record->sourceModuleId],
                        [
                            'academic_year_id' => $record->academicYearId,
                            'term_id' => $record->termId,
                            'title' => $record->title,
                            'description' => $record->description,
                            'starts_at' => $record->startsAt,
                            'ends_at' => $record->endsAt,
                            'is_all_day' => $record->isAllDay,
                            'location' => $record->location,
                            'audience_scope' => $record->audienceScope ?? $definition->defaultAudienceScope,
                            'audience_scope_id' => $record->audienceScopeId,
                            'colour' => $record->colour ?? $definition->defaultColour,
                            'is_public' => $record->isPublic,
                            'rebuilt_at' => Carbon::now(),
                        ],
                    );
                });

                $rebuiltCount++;
            }

            $stale = CalendarEvent::where('school_id', $schoolId)
                ->where('source_type', $sourceType)
                ->whereNotIn('source_module_id', $seenModuleIds === [] ? [-1] : $seenModuleIds)
                ->get();

            foreach ($stale as $event) {
                $this->notifyRegistrantsOfCancellation($event);

                if (! EventRegistration::where('calendar_event_id', $event->id)->exists()) {
                    $event->delete();
                }
            }
        }

        return $rebuiltCount;
    }

    private function notifyRegistrantsOfCancellation(CalendarEvent $event): void
    {
        $registration = EventRegistration::where('calendar_event_id', $event->id)->first();

        if ($registration === null) {
            return;
        }

        $attendees = EventAttendee::where('registration_id', $registration->id)
            ->whereIn('status', ['registered', 'paid', 'checked_in'])
            ->get();

        foreach ($attendees as $attendee) {
            $attendee->update(['status' => 'cancelled']);

            if ($attendee->guardian_id === null) {
                continue;
            }

            $guardian = Guardian::where('school_id', $event->school_id)->find($attendee->guardian_id);

            if ($guardian === null || $guardian->user_id === null) {
                continue;
            }

            try {
                $this->dispatchNotification->execute(new DispatchNotificationData(
                    schoolId: $event->school_id,
                    notificationKey: 'comms.calendar_event_cancelled',
                    recipientType: 'guardian',
                    addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                    context: ['event' => ['title' => $event->title]],
                    recipientId: $guardian->id,
                    relatedType: 'calendar_event',
                    relatedId: $event->id,
                ));
            } catch (Throwable) {
                // A notification-dispatch failure never blocks the rebuild itself.
            }
        }
    }
}
