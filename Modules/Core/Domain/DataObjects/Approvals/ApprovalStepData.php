<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Approvals;

final readonly class ApprovalStepData
{
    /**
     * @param  array<int, array{field: string, operator: string, value: mixed}>|null  $conditionRules
     */
    public function __construct(
        public int $stepNumber,
        public string $name,
        public string $approverType,
        public string $mode,
        public ?int $approverRoleId = null,
        public ?int $approverUserId = null,
        public ?string $dynamicResolver = null,
        public int $requiredApprovals = 1,
        public ?array $conditionRules = null,
        public ?int $escalateAfterHours = null,
        public ?int $escalateToRoleId = null,
        public bool $canReject = true,
        public bool $canReturn = true,
        public bool $requiresComment = false,
    ) {}
}
