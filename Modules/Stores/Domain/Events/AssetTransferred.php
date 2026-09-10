<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\FixedAsset;

final class AssetTransferred
{
    public function __construct(
        public readonly FixedAsset $asset,
    ) {}
}
