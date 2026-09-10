<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

/**
 * Book F BRD-03 §3 ⭐ — who is standing at the gate, as claimed.
 * Never trusted on its own; `CollectionAuthorityChecker` is what
 * turns this into a decision.
 */
final readonly class CollectionClaim
{
    public function __construct(
        public string $name,
        public ?int $guardianId = null,
        public ?string $idNo = null,
        public ?string $claimedRelationship = null,
        public bool $identityVerified = false,
    ) {}
}
