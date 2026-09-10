<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Approvals;

final readonly class RevokeDelegationData
{
    public function __construct(
        public int $delegationId,
    ) {}
}
