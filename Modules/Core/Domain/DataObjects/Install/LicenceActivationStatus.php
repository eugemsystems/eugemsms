<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

/**
 * BR-CORE-01-007: licence activation failure never blocks installation
 * — the system installs in a 14-day grace state instead.
 */
enum LicenceActivationStatus: string
{
    case Activated = 'activated';
    case Grace = 'grace';
    case Invalid = 'invalid';
}
