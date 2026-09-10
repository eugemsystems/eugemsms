<?php

use Illuminate\Support\Facades\File;
use Modules\Core\Domain\Actions\Install\FinaliseInstallationAction;
use Modules\Core\Domain\DataObjects\Install\FinaliseInstallationData;
use Modules\Core\Domain\Support\Install\DeploymentMode;
use Modules\Core\Domain\Support\Install\InstallProgress;
use Modules\Core\Models\SystemInstallation;

afterEach(function (): void {
    // storage_path() is real (not faked) even under RefreshDatabase, so
    // the lock file this test writes must never be left behind — it
    // would 404 every installer route in this actual dev environment.
    File::delete(storage_path('installed.lock'));
});

it('writes installed.lock and records the installation exactly once', function (): void {
    expect(InstallProgress::isInstalled())->toBeFalse();

    $installation = app(FinaliseInstallationAction::class)->execute(new FinaliseInstallationData(
        deploymentMode: DeploymentMode::Saas,
        licenceKey: null,
        licenceActivatedAt: null,
        licenceExpiresAt: now()->addDays(14),
    ));

    expect(InstallProgress::isInstalled())->toBeTrue()
        ->and(SystemInstallation::count())->toBe(1)
        ->and($installation->installation_uuid)->not->toBeEmpty();

    $lockContents = json_decode(File::get(storage_path('installed.lock')), true);
    expect($lockContents['installation_uuid'])->toBe($installation->installation_uuid);
});
