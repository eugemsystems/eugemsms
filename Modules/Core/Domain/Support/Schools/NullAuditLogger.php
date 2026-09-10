<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Schools;

use App\Models\User;
use Modules\Core\Domain\Contracts\Schools\AuditLogger;

final class NullAuditLogger implements AuditLogger
{
    public function record(User $user, string $event, array $context = []): void
    {
        // No-op until CORE-08 binds a real implementation.
    }

    public function securityEvent(?User $user, string $event, array $context = []): void
    {
        // No-op until CORE-08 binds a real implementation.
    }
}
