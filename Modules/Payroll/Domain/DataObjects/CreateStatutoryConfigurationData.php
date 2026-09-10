<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateStatutoryConfigurationData
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public string $configType,
        public array $configuration,
        public CarbonInterface $effectiveFrom,
        public int $createdByUserId,
        public ?int $schoolId = null,
        public ?string $currency = null,
        public ?string $sourceReference = null,
        public bool $requiresConfirmation = false,
    ) {}
}
