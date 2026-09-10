<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Academic\Domain\Support\CycleDayResolver;
use Modules\Academic\Models\Timetable;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\Term;
use Modules\Facilities\Models\BookableResource;
use Modules\Facilities\Models\ResourceBooking;

/**
 * ACT-CheckResourceAvailability (Book H2 OPS-05 §3 ⭐/BR-OPS-05-001/
 * 002/AC-OPS-05-001/002). Read-only — checks two things, either of
 * which refuses a booking, naming the reason: (1) overlap against
 * another booking's own window INCLUDING its setup/cleanup buffer
 * (BR-OPS-05-002 — a hall needing 90 minutes of setup is unavailable
 * for that period, not merely noted), and (2) for a venue-linked
 * resource, a real clash against `ACA-03`'s published timetable —
 * teaching always wins (BR-OPS-05-001). The timetable side follows
 * the exact date → cycle-day translation
 * `CreateSubstitutionsForLeaveAction` already uses, since no venue-
 * occupancy-by-date/time lookup existed anywhere in this codebase
 * before this action.
 */
final class CheckResourceAvailabilityAction extends Action
{
    public function __construct(
        private readonly CycleDayResolver $cycleDayResolver,
    ) {}

    public function execute(int $resourceId, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $excludeBookingId = null): ?string
    {
        $resource = BookableResource::findOrFail($resourceId);

        $setupFrom = $startsAt->copy()->subMinutes($resource->requires_setup_minutes);
        $cleanupUntil = $endsAt->copy()->addMinutes($resource->requires_cleaning_minutes);

        $overlapping = ResourceBooking::where('resource_id', $resource->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->when($excludeBookingId !== null, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->where(function ($query) use ($setupFrom, $cleanupUntil): void {
                $query->where('setup_from', '<', $cleanupUntil)->where('cleanup_until', '>', $setupFrom);
            })
            ->exists();

        if ($overlapping) {
            return 'This resource is already booked for an overlapping window, including setup/cleaning buffers (BR-OPS-05-002).';
        }

        if ($resource->venue_id !== null) {
            return $this->checkTimetableClash($resource->venue_id, $resource->school_id, $setupFrom, $cleanupUntil);
        }

        return null;
    }

    private function checkTimetableClash(int $venueId, int $schoolId, CarbonInterface $windowStart, CarbonInterface $windowEnd): ?string
    {
        $timetables = Timetable::where('school_id', $schoolId)
            ->where('status', 'published')
            ->whereHas('slots', fn ($query) => $query->where('venue_id', $venueId))
            ->with(['slots' => fn ($query) => $query->where('venue_id', $venueId)->with('periodSlot', 'subject'), 'structure'])
            ->get();

        if ($timetables->isEmpty()) {
            return null;
        }

        $cursor = $windowStart->copy()->startOfDay();
        $lastDay = $windowEnd->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $date = $cursor->copy();
            $cursor = $cursor->addDay();

            foreach ($timetables as $timetable) {
                if ($date->lt($timetable->effective_from) || ($timetable->effective_to !== null && $date->gt($timetable->effective_to))) {
                    continue;
                }

                $term = Term::find($timetable->term_id);

                if ($term === null) {
                    continue;
                }

                $holidays = CalendarHoliday::where('academic_year_id', $term->academic_year_id)->get();
                $cycleDay = $this->cycleDayResolver->cycleDayFor($date, $term, $holidays, $timetable->structure->cycle_days);

                if ($cycleDay === null) {
                    continue;
                }

                foreach ($timetable->slots->where('cycle_day', $cycleDay) as $slot) {
                    $periodSlot = $slot->periodSlot;

                    if ($periodSlot === null) {
                        continue;
                    }

                    $slotStart = $date->copy()->setTimeFromTimeString($periodSlot->starts_at);
                    $slotEnd = $date->copy()->setTimeFromTimeString($periodSlot->ends_at);

                    if ($slotStart->lt($windowEnd) && $slotEnd->gt($windowStart)) {
                        $subjectName = $slot->subject->name;

                        return "Teaching timetable clash: {$subjectName} at {$periodSlot->starts_at}\u{2013}{$periodSlot->ends_at} on {$date->toDateString()} — teaching always wins (BR-OPS-05-001).";
                    }
                }
            }
        }

        return null;
    }
}
