<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

final readonly class RecordDrillFindingsData
{
    public function __construct(
        public ?string $findings = null,
        public ?string $actionsRequired = null,
    ) {}
}
