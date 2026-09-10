<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ConsolidatedIncidentRegisterEntry
{
    public function __construct(
        public string $source,
        public CarbonInterface $occurredAt,
        public string $type,
        public string $description,
        public ?string $severity,
    ) {}
}
