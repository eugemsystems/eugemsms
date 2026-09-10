<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class RegisterThirdPartyProcessorData
{
    /**
     * @param  array<int, string>  $dataShared
     */
    public function __construct(
        public int $schoolId,
        public string $name,
        public string $processorType,
        public array $dataShared,
        public string $purpose,
        public ?string $country = null,
        public ?int $agreementFileId = null,
        public ?string $agreementExpiresOn = null,
    ) {}
}
