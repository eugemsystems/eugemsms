<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class ViewDisciplinaryCaseData
{
    public function __construct(
        public int $caseId,
        public int $viewedByUserId,
        public ?string $ip = null,
    ) {}
}
