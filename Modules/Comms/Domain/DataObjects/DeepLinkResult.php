<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class DeepLinkResult
{
    public function __construct(
        public string $screen,
        public bool $degraded,
    ) {}
}
