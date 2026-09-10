<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

final readonly class CreateTriggerRuleData
{
    public function __construct(
        public int $schoolId,
        public string $name,
        public string $triggerType,
        public int $suggestedSanctionId,
        public ?int $demeritThreshold = null,
        public ?int $windowDays = null,
        public ?int $categoryId = null,
        public ?int $repeatCount = null,
        public ?int $notifyRoleId = null,
        public bool $isAutomatic = false,
    ) {}
}
