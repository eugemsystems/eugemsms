<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

/**
 * BR-CORE-01-005: the target schema must be empty or contain only sERP
 * tables. A schema with foreign tables is rejected with an explicit
 * message naming the conflicting tables (AC-CORE-01-005).
 */
enum DatabaseSchemaState: string
{
    case Empty = 'empty';
    case Serp = 'serp';
    case Conflicting = 'conflicting';
}
