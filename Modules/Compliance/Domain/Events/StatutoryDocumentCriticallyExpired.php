<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Events;

use Modules\Compliance\Models\StatutoryDocument;

/**
 * Book H3 CMP-04 §3/BR-CMP-04-006. An expired operating licence,
 * insurance or fire certificate — a critical-alert category.
 */
final class StatutoryDocumentCriticallyExpired
{
    public function __construct(
        public readonly StatutoryDocument $document,
    ) {}
}
