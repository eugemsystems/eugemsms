<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class LicenceKeyData
{
    public function __construct(
        public ?string $key,
    ) {}
}
