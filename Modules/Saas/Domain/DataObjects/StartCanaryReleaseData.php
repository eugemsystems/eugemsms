<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class StartCanaryReleaseData
{
    /**
     * @param  array<int, int>  $canaryTenantIds
     */
    public function __construct(
        public string $version,
        public array $canaryTenantIds,
    ) {}
}
