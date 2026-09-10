<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\AssetVerification;

final class AssetNotFound
{
    public function __construct(
        public readonly AssetVerification $verification,
    ) {}
}
