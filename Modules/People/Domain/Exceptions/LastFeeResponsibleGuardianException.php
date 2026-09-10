<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-03 §6/BR-PPL-03-004/022 (AC-PPL-03-009). A learner's last
 * `is_fee_responsible` relationship cannot be deactivated without
 * naming a replacement first — the residual payer of last resort must
 * always exist.
 */
class LastFeeResponsibleGuardianException extends DomainException
{
    public static function forStudent(int $studentId): self
    {
        return new self(
            "Student [{$studentId}] has no other fee-responsible guardian — name a replacement before deactivating this one.",
            ['student_id' => $studentId],
        );
    }

    public function errorCode(): string
    {
        return 'LAST_FEE_RESPONSIBLE_GUARDIAN';
    }
}
