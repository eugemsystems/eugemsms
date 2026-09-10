<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Sessions\ReopenPeriodAction;
use Modules\Core\Domain\Actions\Sessions\RequestPeriodReopenAction;
use Modules\Core\Domain\DataObjects\Sessions\ReopenPeriodData;
use Modules\Core\Domain\DataObjects\Sessions\RequestPeriodReopenData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

it('requests a reopen for a locked period', function (): void {
    $school = School::factory()->create();
    $requester = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'locked']);

    $request = app(RequestPeriodReopenAction::class)->execute(new RequestPeriodReopenData(
        termId: $term->id,
        periodType: PeriodType::Financial,
        reason: 'A late correction was discovered after close and needs posting.',
        requestedByUserId: $requester->id,
    ));

    expect($request->status)->toBe('pending');
});

it('refuses to request a reopen for a period that is not locked', function (): void {
    $school = School::factory()->create();
    $requester = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'open']);

    app(RequestPeriodReopenAction::class)->execute(new RequestPeriodReopenData(
        $term->id, PeriodType::Financial, 'A reason long enough to pass the length check.', $requester->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('refuses a reason under 20 characters', function (): void {
    $school = School::factory()->create();
    $requester = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'locked']);

    app(RequestPeriodReopenAction::class)->execute(new RequestPeriodReopenData(
        $term->id, PeriodType::Financial, 'too short', $requester->id,
    ));
})->throws(ValidationException::class);

it('refuses a second pending request for the same period', function (): void {
    $school = School::factory()->create();
    $requester = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'locked']);

    app(RequestPeriodReopenAction::class)->execute(new RequestPeriodReopenData(
        $term->id, PeriodType::Financial, 'A late correction was discovered after close and needs posting.', $requester->id,
    ));
    app(RequestPeriodReopenAction::class)->execute(new RequestPeriodReopenData(
        $term->id, PeriodType::Financial, 'Another reason long enough to pass the length check.', $requester->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('approves a pending request and actually reopens the period (AC-CORE-03-002)', function (): void {
    $school = School::factory()->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'locked']);

    $request = app(RequestPeriodReopenAction::class)->execute(new RequestPeriodReopenData(
        $term->id, PeriodType::Financial, 'A late correction was discovered after close and needs posting.', $requester->id,
    ));

    // The period must remain locked until a *different* user approves.
    expect($term->fresh()->financial_state)->toBe(PeriodState::Locked);

    $updatedTerm = app(ReopenPeriodAction::class)->execute(new ReopenPeriodData($request->id, $approver->id));

    expect($updatedTerm->financial_state)->toBe(PeriodState::Open)
        ->and($request->fresh()->status)->toBe('approved')
        ->and($request->fresh()->approved_by)->toBe($approver->id);
});

it('refuses to approve a request with the same user who requested it', function (): void {
    $school = School::factory()->create();
    $requester = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'locked']);

    $request = app(RequestPeriodReopenAction::class)->execute(new RequestPeriodReopenData(
        $term->id, PeriodType::Financial, 'A late correction was discovered after close and needs posting.', $requester->id,
    ));

    app(ReopenPeriodAction::class)->execute(new ReopenPeriodData($request->id, $requester->id));
})->throws(InvalidStateTransitionException::class);

it('refuses to approve an already-approved request', function (): void {
    $school = School::factory()->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $anotherApprover = User::factory()->create();
    $term = Term::factory()->for($school)->create(['financial_state' => 'locked']);

    $request = app(RequestPeriodReopenAction::class)->execute(new RequestPeriodReopenData(
        $term->id, PeriodType::Financial, 'A late correction was discovered after close and needs posting.', $requester->id,
    ));

    app(ReopenPeriodAction::class)->execute(new ReopenPeriodData($request->id, $approver->id));
    app(ReopenPeriodAction::class)->execute(new ReopenPeriodData($request->id, $anotherApprover->id));
})->throws(InvalidStateTransitionException::class);
