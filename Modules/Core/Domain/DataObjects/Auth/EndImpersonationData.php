<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class EndImpersonationData
{
    public function __construct(
        public int $impersonationSessionId,
    ) {}
}
