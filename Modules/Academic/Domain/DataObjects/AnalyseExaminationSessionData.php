<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class AnalyseExaminationSessionData
{
    public function __construct(
        public int $sessionId,
    ) {}
}
