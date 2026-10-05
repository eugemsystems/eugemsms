<?php

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Sessions\GenerateTermWeeksAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Core\Models\TermWeek;

it('generates weeks covering the whole term and computes teaching days (BR-CORE-03-004)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    // A single, exact two-week term: Monday 2026-01-05 to Friday 2026-01-16.
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => Carbon::parse('2026-01-05'),
        'ends_on' => Carbon::parse('2026-01-16'),
    ]);

    $weeks = app(GenerateTermWeeksAction::class)->execute($term);

    expect($weeks)->toHaveCount(2);
    // 2 full Mon-Fri weeks = 10 weekdays, no holidays, no half-term.
    expect($term->fresh()->teaching_days)->toBe(10);
});

it('excludes calendar holidays from teaching days', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => Carbon::parse('2026-01-05'),
        'ends_on' => Carbon::parse('2026-01-16'),
    ]);

    CalendarHoliday::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => Carbon::parse('2026-01-07'),
        'ends_on' => Carbon::parse('2026-01-07'),
        'type' => 'public',
    ]);

    app(GenerateTermWeeksAction::class)->execute($term);

    expect($term->fresh()->teaching_days)->toBe(9);
});

it('excludes the half-term week from teaching days and flags it', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    // Weeks start on Monday by default (`academic.week_starts_on`), so week 2
    // is Mon 2026-01-12 to Sun 2026-01-18; a week only counts as "half term"
    // when the declared range fully contains the calendar week.
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => Carbon::parse('2026-01-05'),
        'ends_on' => Carbon::parse('2026-01-16'),
        'half_term_starts_on' => Carbon::parse('2026-01-12'),
        'half_term_ends_on' => Carbon::parse('2026-01-18'),
    ]);

    $weeks = app(GenerateTermWeeksAction::class)->execute($term);

    expect($term->fresh()->teaching_days)->toBe(5);
    expect($weeks->last()->is_teaching_week)->toBeFalse()
        ->and($weeks->last()->label)->toBe('Half Term');
});

it('starts each week on Monday by default regardless of the server locale', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => Carbon::parse('2026-01-05'),
        'ends_on' => Carbon::parse('2026-01-16'),
    ]);

    Carbon::setLocale('en_US');

    try {
        $weeks = app(GenerateTermWeeksAction::class)->execute($term);
    } finally {
        Carbon::setLocale('en');
    }

    expect($weeks->first()->ends_on->toDateString())->toBe('2026-01-11')
        ->and($weeks->last()->starts_on->toDateString())->toBe('2026-01-12');
});

it('starts each week on Sunday when the school setting says so (academic.week_starts_on)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => Carbon::parse('2026-01-05'),
        'ends_on' => Carbon::parse('2026-01-16'),
    ]);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        key: 'academic.week_starts_on', scopeType: SettingScope::School, scopeId: $school->id, value: 'sunday',
    ));

    $weeks = app(GenerateTermWeeksAction::class)->execute($term);

    expect($weeks->first()->ends_on->toDateString())->toBe('2026-01-10')
        ->and($weeks->last()->starts_on->toDateString())->toBe('2026-01-11');
});

it('regenerating weeks replaces the prior set rather than duplicating it', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => Carbon::parse('2026-01-05'),
        'ends_on' => Carbon::parse('2026-01-16'),
    ]);

    app(GenerateTermWeeksAction::class)->execute($term);
    app(GenerateTermWeeksAction::class)->execute($term);

    expect(TermWeek::withoutGlobalScopes()->where('term_id', $term->id)->count())->toBe(2);
});
