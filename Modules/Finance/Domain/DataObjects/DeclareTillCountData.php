<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class DeclareTillCountData
{
    /**
     * @param  array<string, int>  $declaredClosing
     */
    public function __construct(
        public int $tillSessionId,
        public array $declaredClosing,
        public int $declaredByUserId,
    ) {}
}
