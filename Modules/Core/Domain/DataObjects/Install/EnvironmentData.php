<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

use Modules\Core\Domain\Support\Install\DeploymentMode;

final readonly class EnvironmentData
{
    public function __construct(
        public string $appName,
        public string $appUrl,
        public string $timezone,
        public string $locale,
        public DeploymentMode $deploymentMode,
        public DatabaseCredentialsData $database,
    ) {}
}
