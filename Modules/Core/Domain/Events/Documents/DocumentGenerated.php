<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Documents;

use Modules\Core\Models\Document;

final class DocumentGenerated
{
    public function __construct(
        public readonly Document $document,
    ) {}
}
