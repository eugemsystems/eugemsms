<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Livewire\Sessions\Calendar;
use Modules\Core\Livewire\Sessions\CloseChecklist;
use Modules\Core\Livewire\Sessions\PeriodControl;
use Modules\Core\Livewire\Sessions\RolloverHistory;
use Modules\Core\Livewire\Sessions\RolloverWizard;
use Modules\Core\Livewire\Sessions\Snapshots;
use Modules\Core\Livewire\Sessions\TermDetail;
use Modules\Core\Livewire\Sessions\TransitionLog;
use Modules\Core\Livewire\Sessions\Years;
use Modules\Core\Livewire\Sessions\YearWizard;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\PeriodReopenRequest;
use Modules\Core\Models\PeriodRollover;
use Modules\Core\Models\PeriodSnapshot;
use Modules\Core\Models\PeriodStateTransition;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

function assignedSchoolForSessions(User $user): School
{
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    return $school;
}

it('lists academic years for the school and creates a term via the inline panel', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create(['name' => '2027']);

    Livewire::actingAs($user)
        ->test(Years::class, ['school' => $school])
        ->assertSee('2027')
        ->call('selectYear', $year->id)
        ->set('termNumber', 1)
        ->set('termName', 'Term 1')
        ->set('termStartsOn', '2027-01-11')
        ->set('termEndsOn', '2027-04-10')
        ->call('createTerm')
        ->assertHasNoErrors();

    expect(Term::where('academic_year_id', $year->id)->where('name', 'Term 1')->exists())->toBeTrue();
});

it('still shows the selected year\'s terms after SchoolContext resets between requests', function (): void {
    // Regression test for the real "needs a refresh" bug: in production,
    // every wire:click after the initial page load is its own fresh HTTP
    // request — Livewire does not re-run mount() — so SchoolContext (a
    // per-request singleton, set inside InteractsWithSchool::loadSchool()
    // which only runs from mount()) would be unset for that follow-up
    // request unless InteractsWithSchool::bootInteractsWithSchool() (a
    // Livewire boot-hook, which DOES run on every request) re-establishes
    // it. Livewire::test() keeps every ->call() in the same PHP process,
    // so SchoolContext otherwise stays set for the whole test and would
    // never reproduce this — clearing it here between the initial mount
    // and the follow-up call simulates the real fresh-request boundary.
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['name' => 'Term 1']);

    $component = Livewire::actingAs($user)->test(Years::class, ['school' => $school]);

    SchoolContext::clear();

    $component->call('selectYear', $year->id)->assertSee('Term 1');
});

it('edits an academic year, including setting it as the current year (BR-CORE-03-001)', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $oldCurrent = AcademicYear::factory()->for($school)->create(['is_current' => true, 'name' => '2025']);
    $year = AcademicYear::factory()->for($school)->create(['name' => '2026']);

    Livewire::actingAs($user)
        ->test(Years::class, ['school' => $school])
        ->call('openEditYearModal', $year->id)
        ->assertSet('editYearName', '2026')
        ->set('editYearName', '2026 (Revised)')
        ->set('editYearIsCurrent', true)
        ->call('updateYear')
        ->assertHasNoErrors();

    expect($year->fresh())->name->toBe('2026 (Revised)')->is_current->toBeTrue();
    expect($oldCurrent->fresh()->is_current)->toBeFalse();
});

it('sets a year as current via the row action without opening the edit modal', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create(['is_current' => false]);

    Livewire::actingAs($user)
        ->test(Years::class, ['school' => $school])
        ->call('setCurrentYear', $year->id)
        ->assertHasNoErrors();

    expect($year->fresh()->is_current)->toBeTrue();
});

it('deletes an academic year and refuses to delete the current one', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create(['academic_state' => 'planned', 'financial_state' => 'planned']);
    $currentYear = AcademicYear::factory()->for($school)->create(['is_current' => true]);

    $component = Livewire::actingAs($user)->test(Years::class, ['school' => $school]);

    $component->call('deleteYear', $year->id);
    expect(AcademicYear::withoutGlobalScopes()->find($year->id))->toBeNull();

    $component->call('deleteYear', $currentYear->id)->assertDispatched('toast', variant: 'danger');
    expect(AcademicYear::withoutGlobalScopes()->find($currentYear->id))->not->toBeNull();
});

it('edits and deletes a term from the inline panel', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'number' => 1,
        'name' => 'Term 1',
        'academic_state' => 'planned',
        'financial_state' => 'planned',
    ]);

    $component = Livewire::actingAs($user)
        ->test(Years::class, ['school' => $school])
        ->call('selectYear', $year->id)
        ->call('openEditTermModal', $term->id)
        ->assertSet('editTermName', 'Term 1')
        ->set('editTermName', 'Term One')
        ->call('updateTerm')
        ->assertHasNoErrors();

    expect($term->fresh()->name)->toBe('Term One');

    $component->call('deleteTerm', $term->id);
    expect(Term::withoutGlobalScopes()->find($term->id))->toBeNull();
});

it('creates an academic year with three even terms via the wizard', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);

    Livewire::actingAs($user)
        ->test(YearWizard::class, ['school' => $school])
        ->set('name', '2028')
        ->set('startsOn', '2028-01-01')
        ->set('endsOn', '2028-12-31')
        ->set('generateThreeTerms', true)
        ->call('create')
        ->assertHasNoErrors();

    $year = AcademicYear::where('school_id', $school->id)->where('name', '2028')->sole();
    expect(Term::where('academic_year_id', $year->id)->count())->toBe(3);
});

it('generates teaching weeks for a term', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => '2027-01-11',
        'ends_on' => '2027-04-02',
    ]);

    Livewire::actingAs($user)
        ->test(TermDetail::class, ['school' => $school, 'term' => $term])
        ->call('generateWeeks')
        ->assertHasNoErrors();

    expect($term->fresh()->weeks()->count())->toBeGreaterThan(0);
    expect($term->fresh()->teaching_days)->not->toBeNull();
});

it('opens a planned period and then soft-closes it', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'academic_state' => 'planned',
        'financial_state' => 'planned',
    ]);

    $component = Livewire::actingAs($user)
        ->test(PeriodControl::class, ['school' => $school, 'term' => $term, 'periodType' => 'academic'])
        ->call('transitionTo', 'open');

    expect($term->fresh()->academic_state->value)->toBe('open');

    $component->call('transitionTo', 'soft_closed');

    expect($term->fresh()->academic_state->value)->toBe('soft_closed');
});

it('requires a reason to reopen a soft-closed period and applies it once given', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->softClosed()->create();

    $component = Livewire::actingAs($user)
        ->test(PeriodControl::class, ['school' => $school, 'term' => $term, 'periodType' => 'academic'])
        ->call('transitionTo', 'open')
        ->assertSet('showReasonModal', true);

    expect($term->fresh()->academic_state->value)->toBe('soft_closed');

    $component->set('reason', 'too short')->call('confirmReasonedTransition')->assertHasErrors('reason');

    expect($term->fresh()->academic_state->value)->toBe('soft_closed');

    $component->set('reason', 'Finance team found a posting error that needs correcting.')
        ->call('confirmReasonedTransition')
        ->assertHasNoErrors();

    expect($term->fresh()->academic_state->value)->toBe('open');
});

it('requires a different user to approve a locked-period reopen request', function (): void {
    $requester = User::factory()->create();
    $school = assignedSchoolForSessions($requester);
    $approver = User::factory()->create();
    $approver->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->locked()->create();

    Livewire::actingAs($requester)
        ->test(PeriodControl::class, ['school' => $school, 'term' => $term, 'periodType' => 'academic'])
        ->set('requestReason', 'Bursar needs to correct a misposted fee payment from last week.')
        ->call('requestReopen')
        ->assertHasNoErrors();

    $request = PeriodReopenRequest::where('term_id', $term->id)->sole();
    expect($request->status)->toBe('pending');

    // The requester cannot approve their own request.
    Livewire::actingAs($requester)
        ->test(PeriodControl::class, ['school' => $school, 'term' => $term, 'periodType' => 'academic'])
        ->call('approveReopen', $request->id)
        ->assertDispatched('toast', variant: 'danger');

    expect($term->fresh()->academic_state->value)->toBe('locked');
    expect($request->fresh()->status)->toBe('pending');

    // A different user can.
    Livewire::actingAs($approver)
        ->test(PeriodControl::class, ['school' => $school, 'term' => $term, 'periodType' => 'academic'])
        ->call('approveReopen', $request->id)
        ->assertHasNoErrors();

    expect($term->fresh()->academic_state->value)->toBe('open');
    expect($request->fresh()->status)->toBe('approved');
});

it('renders the close checklist for a period without erroring', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->softClosed()->create();

    Livewire::actingAs($user)
        ->test(CloseChecklist::class, ['school' => $school, 'term' => $term, 'periodType' => 'financial'])
        ->assertSee($term->name);
});

it('initiates a rollover between two terms', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $fromTerm = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 1]);
    $toTerm = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 2]);

    Livewire::actingAs($user)
        ->test(RolloverWizard::class, ['school' => $school])
        ->set('fromTermId', (string) $fromTerm->id)
        ->set('toTermId', (string) $toTerm->id)
        ->call('initiate')
        ->assertHasNoErrors();

    $rollover = PeriodRollover::where('school_id', $school->id)->sole();
    expect($rollover->from_term_id)->toBe($fromTerm->id);
    expect($rollover->to_term_id)->toBe($toTerm->id);
    expect($rollover->status->value)->toBeIn(['pending', 'failed']);
});

it('rejects rolling a term into itself', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

    Livewire::actingAs($user)
        ->test(RolloverWizard::class, ['school' => $school])
        ->set('fromTermId', (string) $term->id)
        ->set('toTermId', (string) $term->id)
        ->call('initiate')
        ->assertHasErrors('toTermId');

    expect(PeriodRollover::where('school_id', $school->id)->count())->toBe(0);
});

it('lists rollover history and opens the report modal', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $fromTerm = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 1]);
    $toTerm = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 2]);
    $rollover = PeriodRollover::factory()->create([
        'school_id' => $school->id,
        'from_term_id' => $fromTerm->id,
        'to_term_id' => $toTerm->id,
    ]);

    Livewire::actingAs($user)
        ->test(RolloverHistory::class, ['school' => $school])
        ->call('view', $rollover->id)
        ->assertSet('viewingId', $rollover->id);
});

it('lists period snapshots and opens the detail modal', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $snapshot = PeriodSnapshot::factory()->create([
        'school_id' => $school->id,
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
    ]);

    Livewire::actingAs($user)
        ->test(Snapshots::class, ['school' => $school])
        ->assertSee(str_replace('_', ' ', $snapshot->snapshot_type))
        ->call('view', $snapshot->id)
        ->assertSet('viewingId', $snapshot->id);
});

it('lists the period transition log', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    PeriodStateTransition::factory()->create(['school_id' => $school->id, 'term_id' => $term->id]);

    Livewire::actingAs($user)
        ->test(TransitionLog::class, ['school' => $school])
        ->assertSee('planned')
        ->assertSee('open');
});

it('lists academic calendar holidays for the current year', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSessions($user);
    $year = AcademicYear::factory()->for($school)->create(['is_current' => true]);
    $holiday = CalendarHoliday::factory()->for($school)->for($year, 'academicYear')->create(['name' => 'Independence Day']);

    Livewire::actingAs($user)
        ->test(Calendar::class, ['school' => $school])
        ->assertSet('selectedYearId', $year->id)
        ->assertSee($holiday->name);
});
