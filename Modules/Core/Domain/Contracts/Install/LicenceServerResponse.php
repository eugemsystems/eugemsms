<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Install;

use Carbon\CarbonInterface;

final readonly class LicenceServerResponse
{
    public function __construct(
        public bool $reachable,
        public bool $valid,
        public ?CarbonInterface $expiresAt = null,
        public ?string $message = null,
    ) {}
}
