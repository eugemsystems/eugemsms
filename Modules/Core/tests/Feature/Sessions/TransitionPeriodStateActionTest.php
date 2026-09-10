<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Sessions\TransitionPeriodStateAction;
use Modules\Core\Domain\Contracts\Sessions\CloseChecklistItem;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\DataObjects\Sessions\TransitionPeriodData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Registry\CloseChecklistRegistry;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\PeriodStateTransition;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

afterEach(fn () => CloseChecklistRegistry::clear());

it('opens a planned term with no prior term', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $term = Term::factory()->for($school)->create(['academic_state' => 'planned']);

    $updated = app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Academic,
        toState: PeriodState::Open,
        performedByUserId: $user->id,
    ));

    expect($updated->academic_state)->toBe(PeriodState::Open);
});

it('writes an immutable audit row for every transition (BR-CORE-03-009)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'planned']);

    app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Financial,
        toState: PeriodState::Open,
        performedByUserId: $user->id,
    ));

    $row = PeriodStateTransition::withoutGlobalScopes()->where('term_id', $term->id)->sole();
    expect($row->from_state)->toBe(PeriodState::Planned)
        ->and($row->to_state)->toBe(PeriodState::Open)
        ->and($row->performed_by)->toBe($user->id);
});

it('refuses to open a term whose prior term is not at least soft-closed', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $priorTerm = Term::factory()->for($school)->create([
        'number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-30', 'academic_state' => 'open',
    ]);
    $term = Term::factory()->for($school)->create([
        'number' => 2, 'starts_on' => '2026-05-01', 'ends_on' => '2026-08-31', 'academic_state' => 'planned',
    ]);

    app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Academic,
        toState: PeriodState::Open,
        performedByUserId: $user->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('allows opening once the prior term is soft-closed', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $priorTerm = Term::factory()->for($school)->create([
        'number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-30', 'academic_state' => 'soft_closed',
    ]);
    $term = Term::factory()->for($school)->create([
        'number' => 2, 'starts_on' => '2026-05-01', 'ends_on' => '2026-08-31', 'academic_state' => 'planned',
    ]);

    $updated = app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Academic,
        toState: PeriodState::Open,
        performedByUserId: $user->id,
    ));

    expect($updated->academic_state)->toBe(PeriodState::Open);
});

it('refuses any transition not on the legal transitions table', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $term = Term::factory()->for($school)->create(['academic_state' => 'planned']);

    app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Academic,
        toState: PeriodState::Locked,
        performedByUserId: $user->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('refuses to lock while a blocking close checklist item fails (BR-CORE-03-013)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'soft_closed']);

    CloseChecklistRegistry::register(new class implements CloseChecklistItem
    {
        public function code(): string
        {
            return 'trial_balance';
        }

        public function label(): string
        {
            return 'Trial balance';
        }

        public function appliesTo(): PeriodType
        {
            return PeriodType::Financial;
        }

        public function isBlocking(): bool
        {
            return true;
        }

        public function check(Term $term): ChecklistItemResult
        {
            return new ChecklistItemResult('trial_balance', 'Trial balance', false, true, 'Out of balance.');
        }
    });

    app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Financial,
        toState: PeriodState::Locked,
        performedByUserId: $user->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('requires a reason of at least 20 characters to reopen from soft-closed', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'soft_closed']);

    app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Financial,
        toState: PeriodState::Open,
        performedByUserId: $user->id,
        reason: 'too short',
    ));
})->throws(ValidationException::class);

it('refuses to reopen a locked period without a second, distinct approver (BR-CORE-03-010)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'locked']);

    app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Financial,
        toState: PeriodState::Open,
        performedByUserId: $user->id,
        reason: 'A genuine correction needs posting into this period.',
    ));
})->throws(InvalidStateTransitionException::class);

it('refuses to reopen a locked period when the approver is the same as the requester', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'locked']);

    app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Financial,
        toState: PeriodState::Open,
        performedByUserId: $user->id,
        reason: 'A genuine correction needs posting into this period.',
        approvedByUserId: $user->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('reopens a locked period with a valid, distinct second approver', function (): void {
    $school = School::factory()->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'locked']);

    $updated = app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Financial,
        toState: PeriodState::Open,
        performedByUserId: $requester->id,
        reason: 'A genuine correction needs posting into this period.',
        approvedByUserId: $approver->id,
    ));

    expect($updated->financial_state)->toBe(PeriodState::Open);

    $row = PeriodStateTransition::withoutGlobalScopes()->where('term_id', $term->id)->sole();
    expect($row->performed_by)->toBe($requester->id)->and($row->approved_by)->toBe($approver->id);
});

it('stamps the closed_at/closed_by columns when locking', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $term = Term::factory()->for($school)->create(['academic_state' => 'soft_closed']);

    $updated = app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $term->id,
        periodType: PeriodType::Academic,
        toState: PeriodState::Locked,
        performedByUserId: $user->id,
    ));

    expect($updated->academic_closed_at)->not->toBeNull()
        ->and($updated->academic_closed_by)->toBe($user->id);
});
