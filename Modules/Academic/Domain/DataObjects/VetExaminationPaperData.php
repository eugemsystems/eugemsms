<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class VetExaminationPaperData
{
    public function __construct(
        public int $paperId,
        public int $vettedByStaffId,
    ) {}
}
