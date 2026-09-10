<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects\Gateway;

use Carbon\CarbonInterface;

final readonly class DeviceRegistrationResult
{
    /**
     * @param  array<int, string>  $applicableTaxes
     */
    public function __construct(
        public string $certificatePem,
        public CarbonInterface $certificateIssuedAt,
        public CarbonInterface $certificateExpiresAt,
        public array $applicableTaxes,
        public ?int $taxpayerDayMaxHours = null,
        public ?string $operatingMode = null,
    ) {}
}
