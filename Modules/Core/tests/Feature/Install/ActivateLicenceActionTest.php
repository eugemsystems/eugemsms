<?php

use Modules\Core\Domain\Actions\Install\ActivateLicenceAction;
use Modules\Core\Domain\Contracts\Install\LicenceClient;
use Modules\Core\Domain\Contracts\Install\LicenceServerResponse;
use Modules\Core\Domain\DataObjects\Install\LicenceActivationStatus;
use Modules\Core\Domain\DataObjects\Install\LicenceKeyData;

it('continues in a 14-day grace period when no key is supplied', function (): void {
    $result = app(ActivateLicenceAction::class)->execute(new LicenceKeyData(key: null));

    expect($result->status)->toBe(LicenceActivationStatus::Grace)
        ->and(round((float) now()->diffInDays($result->expiresAt, absolute: true)))->toBe(14.0);
});

it('continues in a 14-day grace period when the licence server is unreachable', function (): void {
    // The default HttpLicenceClient reports unreachable when no server
    // URL is configured — AC-CORE-01-004.
    $result = app(ActivateLicenceAction::class)->execute(new LicenceKeyData(key: 'SERP-TEST-KEY'));

    expect($result->status)->toBe(LicenceActivationStatus::Grace);
});

it('activates when the licence server confirms the key is valid', function (): void {
    $this->app->bind(LicenceClient::class, fn () => new class implements LicenceClient
    {
        public function activate(string $licenceKey, string $installationUuid): LicenceServerResponse
        {
            return new LicenceServerResponse(reachable: true, valid: true, expiresAt: now()->addYear());
        }

        public function validate(string $licenceKey, string $installationUuid): LicenceServerResponse
        {
            return $this->activate($licenceKey, $installationUuid);
        }
    });

    $result = app(ActivateLicenceAction::class)->execute(new LicenceKeyData(key: 'SERP-VALID-KEY'));

    expect($result->status)->toBe(LicenceActivationStatus::Activated)
        ->and($result->activatedAt)->not->toBeNull();
});

it('reports an invalid key as invalid, not grace', function (): void {
    $this->app->bind(LicenceClient::class, fn () => new class implements LicenceClient
    {
        public function activate(string $licenceKey, string $installationUuid): LicenceServerResponse
        {
            return new LicenceServerResponse(reachable: true, valid: false, message: 'Unknown key.');
        }

        public function validate(string $licenceKey, string $installationUuid): LicenceServerResponse
        {
            return $this->activate($licenceKey, $installationUuid);
        }
    });

    $result = app(ActivateLicenceAction::class)->execute(new LicenceKeyData(key: 'SERP-BAD-KEY'));

    expect($result->status)->toBe(LicenceActivationStatus::Invalid)
        ->and($result->message)->toBe('Unknown key.');
});
