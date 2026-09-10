<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\TermResult;

/**
 * Book D ACA-05 §9.
 */
final class ResultsComputed
{
    public function __construct(public readonly TermResult $result) {}
}
