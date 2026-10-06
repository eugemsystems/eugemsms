<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class RecordBeneficiaryConditionData
{
    public function __construct(
        public int $beneficiaryId,
        public bool $conditionMet,
    ) {}
}
