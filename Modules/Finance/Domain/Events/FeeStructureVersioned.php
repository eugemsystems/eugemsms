<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\FeeStructure;

final class FeeStructureVersioned
{
    public function __construct(
        public readonly FeeStructure $structure,
        public readonly ?FeeStructure $previousVersion,
    ) {}
}
