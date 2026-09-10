<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\AssetDisposal;

final class AssetDisposed
{
    public function __construct(
        public readonly AssetDisposal $disposal,
    ) {}
}
