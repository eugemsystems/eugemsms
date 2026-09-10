<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Auth;

use Modules\Core\Models\ImpersonationSession;

final class ImpersonationEnded
{
    public function __construct(
        public readonly ImpersonationSession $session,
    ) {}
}
