<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class SignOffAppraisalData
{
    public function __construct(
        public int $appraisalId,
        public ?string $staffComments = null,
    ) {}
}
