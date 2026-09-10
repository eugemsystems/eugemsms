<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Install\LicenceClient;
use Modules\Core\Domain\DataObjects\Install\LicenceActivation;
use Modules\Core\Domain\DataObjects\Install\LicenceActivationStatus;
use Modules\Core\Domain\DataObjects\Install\LicenceKeyData;

/**
 * ACT-ActivateLicence (Book A CORE-01 §3). BR-CORE-01-007: activation
 * failure does not block installation — the system installs in a
 * 14-day grace state and warns daily. A school is never locked out
 * mid-installation by a network problem (AC-CORE-01-004).
 */
final class ActivateLicenceAction extends Action
{
    protected bool $transactional = false;

    private const GRACE_DAYS = 14;

    public function __construct(
        private readonly LicenceClient $client,
    ) {}

    public function execute(LicenceKeyData $data): LicenceActivation
    {
        if ($data->key === null || trim($data->key) === '') {
            return new LicenceActivation(
                status: LicenceActivationStatus::Grace,
                activatedAt: null,
                expiresAt: Carbon::now()->addDays(self::GRACE_DAYS),
                message: 'No licence key supplied. Continuing in a 14-day grace period.',
            );
        }

        $installationUuid = (string) Str::uuid();
        $response = $this->client->activate($data->key, $installationUuid);

        if (! $response->reachable) {
            return new LicenceActivation(
                status: LicenceActivationStatus::Grace,
                activatedAt: null,
                expiresAt: Carbon::now()->addDays(self::GRACE_DAYS),
                message: 'The licence server is unreachable. Continuing in a 14-day grace period.',
            );
        }

        if (! $response->valid) {
            return new LicenceActivation(
                status: LicenceActivationStatus::Invalid,
                activatedAt: null,
                expiresAt: null,
                message: $response->message ?? 'This licence key is invalid.',
            );
        }

        return new LicenceActivation(
            status: LicenceActivationStatus::Activated,
            activatedAt: Carbon::now(),
            expiresAt: $response->expiresAt,
            message: 'Licence activated.',
        );
    }
}
