<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

use Carbon\CarbonInterface;

/**
 * Book A CORE-06 §7/AC-CORE-06-005. Everything `GET /verify/{code}`
 * (public, unauthenticated) is allowed to disclose — document type,
 * issuing school, issue date, and validity. Never content, never any
 * learner/staff-identifying detail.
 */
final readonly class VerificationResult
{
    public function __construct(
        public string $documentType,
        public string $schoolName,
        public CarbonInterface $issuedAt,
        public bool $isValid,
    ) {}
}
