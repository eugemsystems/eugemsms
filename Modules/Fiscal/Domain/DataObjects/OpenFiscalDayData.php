<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects;

final readonly class OpenFiscalDayData
{
    public function __construct(
        public int $deviceId,
        public int $openedByUserId,
    ) {}
}
