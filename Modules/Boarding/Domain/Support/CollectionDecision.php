<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

/**
 * Book F BRD-03 §3 ⭐ — the gate terminal's own output. Rendering it
 * as one unambiguous word ("RELEASE"/"DO NOT RELEASE") is the UI's
 * job (AC-BRD-03-011); this is the decision it renders.
 */
final readonly class CollectionDecision
{
    public function __construct(
        public bool $released,
        public ?string $refusalReason = null,
        public bool $escalate = false,
        public bool $alertSecurity = false,
        public bool $alertHead = false,
    ) {}
}
