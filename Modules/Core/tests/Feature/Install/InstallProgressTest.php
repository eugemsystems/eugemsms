<?php

use Modules\Core\Domain\Support\Install\InstallProgress;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Domain\Support\Install\InstallStepStatus;

it('resumes at the welcome step when nothing has run yet', function (): void {
    expect((new InstallProgress)->resumeStep())->toBe(InstallStepKey::Welcome);
});

it('resumes at the first non-completed step, never step 1 (BR-CORE-01-003)', function (): void {
    $progress = new InstallProgress;

    $progress->markCompleted(InstallStepKey::Welcome);
    $progress->markCompleted(InstallStepKey::Requirements);
    $progress->markCompleted(InstallStepKey::Environment);
    $progress->markCompleted(InstallStepKey::Database);
    $progress->markFailed(InstallStepKey::Migrations, 'Migration 2024_01_01_000000 failed.');

    expect($progress->resumeStep())->toBe(InstallStepKey::Migrations)
        ->and($progress->statusOf(InstallStepKey::Migrations))->toBe(InstallStepStatus::Failed)
        ->and($progress->statusOf(InstallStepKey::Database))->toBe(InstallStepStatus::Completed);
});

it('never re-runs already-completed steps after a resume', function (): void {
    $progress = new InstallProgress;

    $progress->markCompleted(InstallStepKey::Welcome);
    $progress->markCompleted(InstallStepKey::Requirements);

    $resumed = $progress->resumeStep();

    expect($resumed)->not->toBe(InstallStepKey::Welcome)
        ->and($resumed)->not->toBe(InstallStepKey::Requirements);
});

it('merges payload across repeated writes to the same step', function (): void {
    $progress = new InstallProgress;

    $progress->markCompleted(InstallStepKey::Environment, ['app_name' => 'sERP']);
    $progress->markCompleted(InstallStepKey::Environment, ['timezone' => 'Africa/Harare']);

    expect($progress->payloadOf(InstallStepKey::Environment))->toBe([
        'app_name' => 'sERP',
        'timezone' => 'Africa/Harare',
    ]);
});
