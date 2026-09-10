<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

final class DeepLinkResolved
{
    public function __construct(
        public readonly string $linkType,
        public readonly int $linkId,
        public readonly bool $degraded,
    ) {}
}
