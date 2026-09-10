<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Events;

final class BlockedCategoryAttempt
{
    public function __construct(
        public readonly int $walletId,
        public readonly string $category,
    ) {}
}
