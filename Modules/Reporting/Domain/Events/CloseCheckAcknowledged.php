<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Events;

use Modules\Reporting\Models\CloseCheckAcknowledgement;

final class CloseCheckAcknowledged
{
    public function __construct(
        public readonly CloseCheckAcknowledgement $acknowledgement,
    ) {}
}
