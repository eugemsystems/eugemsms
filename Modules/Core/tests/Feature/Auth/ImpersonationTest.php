<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Auth\EndImpersonationAction;
use Modules\Core\Domain\Actions\Auth\StartImpersonationAction;
use Modules\Core\Domain\Actions\Backups\GenerateContractExitExportAction;
use Modules\Core\Domain\DataObjects\Auth\EndImpersonationData;
use Modules\Core\Domain\DataObjects\Auth\StartImpersonationData;
use Modules\Core\Domain\DataObjects\Backups\GenerateContractExitExportData;
use Modules\Core\Domain\Exceptions\ImpersonationNotPermittedException;
use Modules\Core\Domain\Exceptions\ReasonRequiredException;
use Modules\Core\Domain\Support\Auth\ImpersonationGuard;
use Modules\Core\Domain\Support\ImpersonationContext;
use Modules\Core\Models\School;

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

it('resolves the active impersonation session into ImpersonationContext from a real request (SetImpersonationContext)', function (): void {
    $engineer = User::factory()->create();
    $bursar = User::factory()->create();

    $session = app(StartImpersonationAction::class)->execute(new StartImpersonationData(
        impersonatorId: $engineer->id,
        impersonatedId: $bursar->id,
        reason: 'Investigating a stuck receipt.',
        ticketReference: 'SUP-1',
    ));

    $this->actingAs($bursar)
        ->withSession(['impersonator_id' => $engineer->id, 'impersonation_session_id' => $session->id])
        ->get(route('dashboard'));

    expect(ImpersonationContext::current()?->id)->toBe($session->id);
});

it('leaves ImpersonationContext empty for an ordinary, non-impersonated request', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'));

    expect(ImpersonationContext::current())->toBeNull();
});

it('blocks GenerateContractExitExportAction while impersonating, via Action::assertNotImpersonating (BR-CORE-05-018)', function (): void {
    $engineer = User::factory()->create();
    $bursar = User::factory()->create();
    $school = School::factory()->create();

    $session = app(StartImpersonationAction::class)->execute(new StartImpersonationData(
        impersonatorId: $engineer->id,
        impersonatedId: $bursar->id,
        reason: 'Investigating a stuck receipt.',
        ticketReference: 'SUP-1',
    ));

    ImpersonationContext::set($session);

    expect(fn () => app(GenerateContractExitExportAction::class)->execute(new GenerateContractExitExportData(
        schoolId: $school->id,
        requestedByUserId: $bursar->id,
    )))->toThrow(ImpersonationNotPermittedException::class);

    ImpersonationContext::clear();
});

it('still generates a contract-exit export normally when nobody is impersonating', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $school = School::factory()->create();

    $file = app(GenerateContractExitExportAction::class)->execute(new GenerateContractExitExportData(
        schoolId: $school->id,
        requestedByUserId: $user->id,
    ));

    expect($file->category)->toBe('contract_exit_export');
});
