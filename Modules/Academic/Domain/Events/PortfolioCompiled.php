<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\ProjectPortfolio;

final class PortfolioCompiled
{
    public function __construct(
        public readonly ProjectPortfolio $portfolio,
    ) {}
}
