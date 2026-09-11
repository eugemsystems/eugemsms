<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class FeatureRolloutAlreadyAtFinalStageException extends DomainException
{
    public function errorCode(): string
    {
        return 'FEATURE_ROLLOUT_ALREADY_AT_FINAL_STAGE';
    }
}
