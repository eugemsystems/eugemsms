<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 FIN-14 §5/BR-FIN-14-004. The spec's "or an explicit
 * wallet-control right" half is a documented deferral — no such
 * right exists on `StudentGuardian` yet, only `is_fee_responsible`,
 * so that is the sole gate this pass enforces.
 */
final class WalletControlRightRequiredException extends DomainException
{
    public static function forGuardian(int $guardianId, int $studentId): self
    {
        return new self(
            "Guardian [{$guardianId}] does not hold fee responsibility for student [{$studentId}] and cannot set wallet controls.",
            ['guardian_id' => $guardianId, 'student_id' => $studentId],
        );
    }

    public function errorCode(): string
    {
        return 'WALLET_CONTROL_RIGHT_REQUIRED';
    }
}
