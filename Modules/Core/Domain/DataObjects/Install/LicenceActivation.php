<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

use Carbon\CarbonInterface;

final readonly class LicenceActivation
{
    public function __construct(
        public LicenceActivationStatus $status,
        public ?CarbonInterface $activatedAt,
        public ?CarbonInterface $expiresAt,
        public string $message,
    ) {}
}
