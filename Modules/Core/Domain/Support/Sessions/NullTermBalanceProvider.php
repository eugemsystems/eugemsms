<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Sessions;

use Modules\Core\Domain\Contracts\Sessions\TermBalanceProvider;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\Term;

final class NullTermBalanceProvider implements TermBalanceProvider
{
    /**
     * @return array<string, Money>
     */
    public function closingBalances(Term $term): array
    {
        return [];
    }

    /**
     * @return array<string, Money>
     */
    public function openingBalances(Term $term): array
    {
        return [];
    }
}
