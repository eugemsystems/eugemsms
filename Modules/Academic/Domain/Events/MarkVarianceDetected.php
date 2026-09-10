<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\ExaminationMark;

final class MarkVarianceDetected
{
    public function __construct(
        public readonly ExaminationMark $mark,
    ) {}
}
