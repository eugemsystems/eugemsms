<?php

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Sessions\GenerateTermWeeksAction;
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
    // A week only counts as "half term" when the declared range fully
    // contains the calendar week (Sunday-start here) — not the term's
    // own clipped range, which can end mid-week. 2026-01-11 is the
    // Sunday that starts week 2; the term itself still ends 01-16.
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => Carbon::parse('2026-01-05'),
        'ends_on' => Carbon::parse('2026-01-16'),
        'half_term_starts_on' => Carbon::parse('2026-01-11'),
        'half_term_ends_on' => Carbon::parse('2026-01-17'),
    ]);

    $weeks = app(GenerateTermWeeksAction::class)->execute($term);

    expect($term->fresh()->teaching_days)->toBe(5);
    expect($weeks->last()->is_teaching_week)->toBeFalse()
        ->and($weeks->last()->label)->toBe('Half Term');
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
