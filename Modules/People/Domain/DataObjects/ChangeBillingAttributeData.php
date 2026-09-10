<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * BR-PPL-01-004 ⭐ — the single path for changing `enrolment_type`,
 * `residency`, `grade_level_id`, `class_id`, `section_id`, or `pathway`.
 */
final readonly class ChangeBillingAttributeData
{
    public function __construct(
        public int $studentId,
        public string $attribute,
        public string $newValue,
        public CarbonInterface $effectiveFrom,
        public int $changedByUserId,
        public ?string $reasonCode = null,
        public ?string $reason = null,
    ) {}
}
