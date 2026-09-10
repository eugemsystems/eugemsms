<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Events;

use Modules\Security\Models\ContractorWorker;

final class ContractorSiteAccessRefused
{
    public function __construct(
        public readonly ContractorWorker $worker,
        public readonly string $reason,
    ) {}
}
