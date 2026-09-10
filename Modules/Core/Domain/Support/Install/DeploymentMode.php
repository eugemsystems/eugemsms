<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

enum DeploymentMode: string
{
    case Saas = 'saas';
    case Dedicated = 'dedicated';
    case OnPremise = 'on_premise';
}
