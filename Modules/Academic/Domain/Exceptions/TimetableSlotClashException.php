<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\TimetableClash;
use Modules\Academic\Models\TimetableSlot;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book E ACA-03 §3/BR-ACA-03-007. All four clash levels are
 * unconditionally hard — this is the refusal, naming the teacher, the
 * venue, or the affected learners, never a bare "clash detected".
 */
class TimetableSlotClashException extends DomainException
{
    /**
     * @param  Collection<int, TimetableClash>  $clashes
     */
    public static function forClashes(Collection $clashes): self
    {
        $existingSlots = TimetableSlot::withoutGlobalScopes()
            ->whereIn('id', $clashes->pluck('slotIdB')->unique())
            ->get()
            ->keyBy('id');

        $messages = [];

        foreach ($clashes as $clash) {
            $existing = $existingSlots->get($clash->slotIdB);
            $messages[] = self::messageFor($clash, $existing);
        }

        return new self(implode(' ', array_unique($messages)), ['clashes' => $clashes->toArray()]);
    }

    private static function messageFor(TimetableClash $clash, ?TimetableSlot $existing): string
    {
        return match ($clash->level) {
            'teacher' => 'Teacher '.self::staffName($existing).' is already teaching another slot at this time.',
            'venue' => 'Venue '.self::venueName($existing).' is already in use at this time.',
            'class' => 'This class is already scheduled for another lesson at this time.',
            'learner' => count($clash->sharedLearnerIds).' learner(s) ('.self::learnerNames($clash->sharedLearnerIds).') would be double-booked at this time.',
            default => 'A scheduling clash was detected.',
        };
    }

    private static function staffName(?TimetableSlot $existing): string
    {
        if ($existing === null) {
            return 'unknown';
        }

        $staff = Staff::find($existing->staff_id);

        if ($staff === null) {
            return 'unknown';
        }

        return $staff->preferred_name ?? $staff->first_name;
    }

    private static function venueName(?TimetableSlot $existing): string
    {
        if ($existing === null || $existing->venue_id === null) {
            return 'unknown';
        }

        $venue = Venue::find($existing->venue_id);

        if ($venue === null) {
            return 'unknown';
        }

        return $venue->name;
    }

    /**
     * @param  array<int, int>  $studentIds
     */
    private static function learnerNames(array $studentIds): string
    {
        return Student::query()
            ->whereIn('id', $studentIds)
            ->get()
            ->map(fn (Student $s): string => "{$s->first_name} {$s->last_name}")
            ->implode(', ');
    }

    public function errorCode(): string
    {
        return 'TIMETABLE_SLOT_CLASH';
    }
}
