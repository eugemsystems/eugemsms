<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class CreatePolicyData
{
    /**
     * @param  array<int, string>  $acknowledgementAudiences
     */
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $title,
        public string $category,
        public string $version,
        public string $effectiveFrom,
        public bool $requiresAcknowledgement = false,
        public array $acknowledgementAudiences = [],
        public ?string $content = null,
        public ?int $documentFileId = null,
        public ?string $reviewDueOn = null,
        public ?int $approvedByUserId = null,
        public ?string $boardApprovedOn = null,
        public ?int $supersedesPolicyId = null,
    ) {}
}
