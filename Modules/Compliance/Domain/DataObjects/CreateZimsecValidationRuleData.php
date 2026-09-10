<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class CreateZimsecValidationRuleData
{
    public function __construct(
        public string $field,
        public string $ruleType,
        public string $severity,
        public string $message,
        public ?int $schoolId = null,
        public ?string $examLevel = null,
        public ?string $ruleValue = null,
    ) {}
}
