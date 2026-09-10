<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\Account;

final class AccountCreated
{
    public function __construct(
        public readonly Account $account,
    ) {}
}
