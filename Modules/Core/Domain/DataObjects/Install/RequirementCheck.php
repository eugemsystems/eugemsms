<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

/**
 * One row of `ACT-VerifyRequirements`' report (Book A CORE-01 §3/§5) —
 * "pass/warn/fail per item with remediation text."
 */
final readonly class RequirementCheck
{
    public function __construct(
        public string $name,
        public RequirementCheckStatus $status,
        public string $message,
        public bool $mandatory,
        public ?string $remediation = null,
    ) {}

    public function passes(): bool
    {
        return $this->status === RequirementCheckStatus::Pass;
    }

    public function blocksInstallation(): bool
    {
        return $this->mandatory && $this->status === RequirementCheckStatus::Fail;
    }
}
