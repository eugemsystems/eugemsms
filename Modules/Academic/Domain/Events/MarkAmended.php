<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\AssessmentMarkVersion;

/**
 * Book D ACA-05 §9 ⚠/BR-ACA-05-009/010.
 */
final class MarkAmended
{
    public function __construct(public readonly AssessmentMarkVersion $version) {}
}
