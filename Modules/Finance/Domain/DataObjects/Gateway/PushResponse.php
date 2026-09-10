<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects\Gateway;

final readonly class PushResponse
{
    public function __construct(
        public string $gatewayReference,
        public ?string $instructions = null,
        public ?string $pollUrl = null,
    ) {}
}
