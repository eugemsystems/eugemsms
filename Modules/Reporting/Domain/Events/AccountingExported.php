<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Events;

use Modules\Reporting\Models\AccountingExport;

final class AccountingExported
{
    public function __construct(
        public readonly AccountingExport $export,
    ) {}
}
