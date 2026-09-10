<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Sessions;

use Modules\Core\Models\Term;

final class TermCreated
{
    public function __construct(
        public readonly Term $term,
    ) {}
}
