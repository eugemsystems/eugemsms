<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\EndImpersonationAction;
use Modules\Core\Domain\Actions\Auth\StartImpersonationAction;
use Modules\Core\Domain\DataObjects\Auth\EndImpersonationData;
use Modules\Core\Domain\DataObjects\Auth\StartImpersonationData;
use Modules\Core\Domain\Exceptions\ImpersonationNotPermittedException;
use Modules\Core\Domain\Exceptions\ReasonRequiredException;
use Modules\Core\Domain\Support\Auth\ImpersonationGuard;

it('starts a time-boxed impersonation session with a reason and ticket reference (BR-CORE-05-017)', function (): void {
    $engineer = User::factory()->create();
    $bursar = User::factory()->create();

    $session = app(StartImpersonationAction::class)->execute(new StartImpersonationData(
        impersonatorId: $engineer->id,
        impersonatedId: $bursar->id,
        reason: 'Investigating a stuck receipt for support ticket 4821.',
        ticketReference: 'SUP-4821',
    ));

    expect($session->isActive())->toBeTrue()
        ->and(abs($session->expires_at->diffInMinutes($session->started_at)))->toBe(60.0);
});

it('refuses to start without a reason or ticket reference', function (): void {
    $engineer = User::factory()->create();
    $bursar = User::factory()->create();

    app(StartImpersonationAction::class)->execute(new StartImpersonationData(
        impersonatorId: $engineer->id,
        impersonatedId: $bursar->id,
        reason: '',
        ticketReference: '',
    ));
})->throws(ReasonRequiredException::class);

it('ends an impersonation session', function (): void {
    $engineer = User::factory()->create();
    $bursar = User::factory()->create();

    $session = app(StartImpersonationAction::class)->execute(new StartImpersonationData(
        impersonatorId: $engineer->id,
        impersonatedId: $bursar->id,
        reason: 'Investigating a stuck receipt.',
        ticketReference: 'SUP-1',
    ));

    $ended = app(EndImpersonationAction::class)->execute(new EndImpersonationData($session->id));

    expect($ended->isActive())->toBeFalse();
});

it('blocks a financial mutation while impersonating and records the block (BR-CORE-05-018/AC-CORE-05-006)', function (): void {
    $engineer = User::factory()->create();
    $bursar = User::factory()->create();

    $session = app(StartImpersonationAction::class)->execute(new StartImpersonationData(
        impersonatorId: $engineer->id,
        impersonatedId: $bursar->id,
        reason: 'Investigating a stuck receipt.',
        ticketReference: 'SUP-1',
    ));

    expect(fn () => app(ImpersonationGuard::class)->assertPermitted($session, ImpersonationGuard::FINANCIAL_MUTATION, 'create a receipt'))
        ->toThrow(ImpersonationNotPermittedException::class);

    expect($session->fresh()->actions_performed)->toHaveCount(1);
});

it('does not block a read-only action while impersonating', function (): void {
    $engineer = User::factory()->create();
    $bursar = User::factory()->create();

    $session = app(StartImpersonationAction::class)->execute(new StartImpersonationData(
        impersonatorId: $engineer->id,
        impersonatedId: $bursar->id,
        reason: 'Investigating a stuck receipt.',
        ticketReference: 'SUP-1',
    ));

    app(ImpersonationGuard::class)->assertPermitted($session, 'view_record', 'view a receipt');

    expect(true)->toBeTrue();
});
