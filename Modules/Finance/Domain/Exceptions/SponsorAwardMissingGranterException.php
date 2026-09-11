<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class SponsorAwardMissingGranterException extends DomainException
{
    public function errorCode(): string
    {
        return 'SPONSOR_AWARD_MISSING_GRANTER';
    }
}
