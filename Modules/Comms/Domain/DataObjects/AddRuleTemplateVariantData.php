<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class AddRuleTemplateVariantData
{
    public function __construct(
        public int $ruleId,
        public string $variantKey,
        public string $templateKey,
        public int $weightPercent,
    ) {}
}
