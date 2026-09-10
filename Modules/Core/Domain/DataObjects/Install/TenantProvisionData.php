<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class TenantProvisionData
{
    public function __construct(
        public string $name,
        public string $slug,
    ) {}
}
