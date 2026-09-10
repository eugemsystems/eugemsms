<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

/**
 * Book I COM-01 §4/§6. Fires when a message forced UCS-2 encoding and
 * so needed more than one segment despite fitting easily within a
 * single GSM-7 segment (≤160 characters) — the "segment waste" the
 * cost report names, almost always a curly quote or an em-dash that
 * slipped past normalisation.
 */
final class SegmentCountExceededExpected
{
    public function __construct(
        public readonly int $schoolId,
        public readonly int $characterCount,
        public readonly int $segmentCount,
    ) {}
}
