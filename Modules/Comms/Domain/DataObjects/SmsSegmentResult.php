<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class SmsSegmentResult
{
    public function __construct(
        public string $normalizedBody,
        public string $encoding,
        public int $characterCount,
        public int $segmentCount,
    ) {}
}
