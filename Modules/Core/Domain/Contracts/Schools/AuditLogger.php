<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Schools;

use App\Models\User;

/**
 * Book A CORE-02 §4. BR-CORE-02-008/009: switching schools writes an
 * audit entry, and an unauthorised switch attempt is logged as a
 * security event. Real activity/audit logging belongs to CORE-08 (not
 * built yet) — `NullAuditLogger` is a documented no-op until it binds a
 * real implementation, same shape as `RecordActivity` middleware's own
 * "full logging belongs to CORE-08" note.
 */
interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(User $user, string $event, array $context = []): void;

    /**
     * @param  array<string, mixed>  $context
     */
    public function securityEvent(?User $user, string $event, array $context = []): void;
}
