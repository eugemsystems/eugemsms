<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\SafeguardingCase;

final class CaseClosed
{
    public function __construct(
        public readonly SafeguardingCase $case,
    ) {}
}
