<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class RecordReturnData
{
    public function __construct(
        public int $exeatId,
        public int $recordedByUserId,
    ) {}
}
