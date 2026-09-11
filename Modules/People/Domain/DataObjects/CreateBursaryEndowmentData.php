<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateBursaryEndowmentData
{
    public function __construct(
        public int $schoolId,
        public string $donorName,
        public string $currency,
        public int $fundsSchemeId,
        public CarbonInterface $startsOn,
        public ?int $alumnusId = null,
        public ?int $endowmentCapitalMinor = null,
        public ?int $annualCommitmentMinor = null,
        public ?string $namedRecognition = null,
        public bool $isAnonymous = false,
    ) {}
}
