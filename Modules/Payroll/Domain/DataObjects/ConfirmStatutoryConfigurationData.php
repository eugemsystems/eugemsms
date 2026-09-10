<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

final readonly class ConfirmStatutoryConfigurationData
{
    public function __construct(
        public int $statutoryConfigurationId,
        public int $confirmedByUserId,
    ) {}
}
