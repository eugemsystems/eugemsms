<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Approvals;

final readonly class CreateApprovalChainData
{
    /**
     * @param  array<int, array{field: string, operator: string, value: mixed}>|null  $conditionRules
     * @param  array<int, ApprovalStepData>  $steps
     */
    public function __construct(
        public int $schoolId,
        public string $approvableType,
        public string $name,
        public array $steps,
        public ?string $description = null,
        public ?array $conditionRules = null,
        public bool $isDefault = false,
        public int $priority = 0,
        public ?int $createdByUserId = null,
    ) {}
}
