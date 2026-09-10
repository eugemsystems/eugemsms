<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class RecordBreachNotificationDecisionData
{
    public function __construct(
        public int $breachId,
        public bool $authorityNotified,
        public bool $subjectsNotified,
    ) {}
}
