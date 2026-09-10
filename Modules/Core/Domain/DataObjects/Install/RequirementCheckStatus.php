<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

enum RequirementCheckStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
}
