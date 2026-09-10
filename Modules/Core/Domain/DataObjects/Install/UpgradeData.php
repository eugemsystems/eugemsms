<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class UpgradeData
{
    public function __construct(
        public string $toVersion,
    ) {}
}
