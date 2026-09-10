<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Contracts\Install\SeedPack;
use Modules\Core\Domain\Contracts\Install\SeedPackOutcome;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * Book A CORE-01 §7 `calendar` pack — "Three-term MoPSE calendar template
 * for the current and next year." Owned by CORE-03, whose `academic_years`
 * / `terms` tables already exist (Book A Part 1), so this pack is real,
 * unlike the others still waiting on their owning module.
 */
final class CalendarSeedPack implements SeedPack
{
    public function code(): string
    {
        return 'calendar';
    }

    public function label(): string
    {
        return 'Calendar';
    }

    public function description(): string
    {
        return 'Three-term MoPSE calendar template for the current and next year.';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(School $school): SeedPackOutcome
    {
        $today = Carbon::today();
        $currentCalendarYear = (int) $today->format('Y');

        foreach ([$currentCalendarYear, $currentCalendarYear + 1] as $calendarYear) {
            $this->createYear($school, $calendarYear, $calendarYear === $currentCalendarYear ? $today : null);
        }

        return new SeedPackOutcome(
            packCode: $this->code(),
            ran: true,
            message: "Created {$currentCalendarYear} and ".($currentCalendarYear + 1).' with three MoPSE terms each.',
        );
    }

    private function createYear(School $school, int $calendarYear, ?Carbon $today): void
    {
        $name = (string) $calendarYear;

        // By the Seed step the installer's admin is already authenticated
        // (Administrator step logs them in), so `withoutSchoolScope()`'s
        // permission-gated escape hatch (BR-GLOBAL-012) would throw —
        // that gate is for an end user asking to see cross-school data,
        // not this system-internal dedup check. `withoutGlobalScopes()`
        // is the same primitive `PeriodGuard` already uses for the same
        // reason: no ambient SchoolContext is set during install, and
        // none is needed since the school is queried explicitly.
        if (AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->where('name', $name)->exists()) {
            return;
        }

        $year = AcademicYear::create([
            'school_id' => $school->id,
            'name' => $name,
            'starts_on' => "{$calendarYear}-01-01",
            'ends_on' => "{$calendarYear}-12-31",
            'is_current' => $today !== null,
            'academic_state' => 'open',
            'financial_state' => 'open',
        ]);

        $terms = [
            [1, 'Term 1', "{$calendarYear}-01-01", "{$calendarYear}-04-30"],
            [2, 'Term 2', "{$calendarYear}-05-01", "{$calendarYear}-08-31"],
            [3, 'Term 3', "{$calendarYear}-09-01", "{$calendarYear}-12-20"],
        ];

        foreach ($terms as [$number, $termName, $startsOn, $endsOn]) {
            $isCurrent = $today !== null && $today->betweenIncluded($startsOn, $endsOn);

            $year->terms()->create([
                'school_id' => $school->id,
                'number' => $number,
                'name' => $termName,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'is_current' => $isCurrent,
                'academic_state' => 'open',
                'financial_state' => 'open',
            ]);
        }
    }
}
