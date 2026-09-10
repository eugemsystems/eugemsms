<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\FinaliseInstallationData;
use Modules\Core\Models\SystemInstallation;

/**
 * ACT-FinaliseInstallation (Book A CORE-01 §3). Caches config+routes+views,
 * writes `installed.lock`, records the installation. BR-CORE-01-001: once
 * this file exists, every installer route 404s. BR-CORE-01-012:
 * `installation_uuid` is generated once here and never regenerated.
 */
final class FinaliseInstallationAction extends Action
{
    public function execute(FinaliseInstallationData $data): SystemInstallation
    {
        $installation = $this->transaction(fn (): SystemInstallation => SystemInstallation::create([
            'installed_at' => now(),
            'installed_version' => config('app.version', '1.0.0'),
            'deployment_mode' => $data->deploymentMode,
            'licence_key' => $data->licenceKey,
            'licence_activated_at' => $data->licenceActivatedAt,
            'licence_expires_at' => $data->licenceExpiresAt,
            'installation_uuid' => (string) Str::uuid(),
            'server_fingerprint' => hash('sha256', gethostname().php_uname()),
        ]));

        $this->optimise();
        $this->writeLock($installation);

        return $installation;
    }

    private function optimise(): void
    {
        if (app()->environment(['testing', 'local'])) {
            return;
        }

        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
    }

    private function writeLock(SystemInstallation $installation): void
    {
        file_put_contents(storage_path('installed.lock'), json_encode([
            'installed_at' => $installation->installed_at->toIso8601String(),
            'installation_uuid' => $installation->installation_uuid,
            'version' => $installation->installed_version,
        ], JSON_PRETTY_PRINT));
    }
}
