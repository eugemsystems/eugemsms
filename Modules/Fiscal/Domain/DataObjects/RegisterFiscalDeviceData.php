<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects;

final readonly class RegisterFiscalDeviceData
{
    public function __construct(
        public int $schoolId,
        public string $deviceId,
        public string $deviceSerial,
        public string $taxpayerName,
        public string $taxpayerTin,
        public string $environment,
        public string $apiBaseUrl,
        public ?string $deviceBranchId = null,
        public ?string $vatNumber = null,
    ) {}
}
