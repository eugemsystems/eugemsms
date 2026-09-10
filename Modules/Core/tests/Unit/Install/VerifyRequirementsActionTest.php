<?php

use Modules\Core\Domain\Actions\Install\VerifyRequirementsAction;
use Modules\Core\Domain\DataObjects\Install\RequirementCheckStatus;

it('reports the PHP version as passing on this runtime', function (): void {
    $report = (new VerifyRequirementsAction)->execute();

    $phpCheck = collect($report->checks)->firstWhere('name', 'PHP version');

    expect($phpCheck)->not->toBeNull()
        ->and($phpCheck->status)->toBe(RequirementCheckStatus::Pass);
});

it('checks every mandatory extension this app actually needs', function (): void {
    $report = (new VerifyRequirementsAction)->execute();

    $names = collect($report->checks)->pluck('name')->all();

    foreach (['pdo', 'mbstring', 'openssl', 'gd', 'zip', 'bcmath', 'intl'] as $extension) {
        expect($names)->toContain("PHP extension: {$extension}");
    }
});

it('passes mandatory when every mandatory check passes on this runtime', function (): void {
    $report = (new VerifyRequirementsAction)->execute();

    expect($report->passesMandatory())->toBeTrue()
        ->and($report->failures())->toBe([]);
});
