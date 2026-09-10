<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class ReconcileLaundryCycleData
{
    public function __construct(
        public int $laundryCycleId,
        public ?int $costMinor = null,
    ) {}
}
