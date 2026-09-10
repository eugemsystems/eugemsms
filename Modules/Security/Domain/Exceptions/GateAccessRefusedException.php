<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H2 OPS-06 §4 ⭐/BR-OPS-06-002/AC-OPS-06-004. The message is
 * always `CheckGateAccessAction`'s own specific reason.
 */
class GateAccessRefusedException extends DomainException
{
    public static function forReason(int $contractorWorkerId, string $reason): self
    {
        return new self(
            "Gate access refused for contractor worker #{$contractorWorkerId}: {$reason}",
            ['contractor_worker_id' => $contractorWorkerId, 'reason' => $reason],
        );
    }

    public function errorCode(): string
    {
        return 'GATE_ACCESS_REFUSED';
    }
}
