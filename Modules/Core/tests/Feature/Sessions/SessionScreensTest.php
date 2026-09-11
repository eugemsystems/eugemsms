<?php

use App\Models\User;
use Livewire\Livewire;
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
