<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\BankStatement;

final class BankStatementImported
{
    public function __construct(
        public readonly BankStatement $statement,
    ) {}
}
