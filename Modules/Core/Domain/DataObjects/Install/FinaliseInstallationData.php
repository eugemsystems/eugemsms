<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Support\Install\DeploymentMode;

final readonly class FinaliseInstallationData
{
    public function __construct(
        public DeploymentMode $deploymentMode,
        public ?string $licenceKey,
        public ?CarbonInterface $licenceActivatedAt,
        public ?CarbonInterface $licenceExpiresAt,
    ) {}
}
