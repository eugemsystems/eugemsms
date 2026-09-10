<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-06 §6, mirroring `MarkAmendmentRequiresApprovalException`
 * (Book D ACA-05). A verified project's mark may only change through
 * Core's CORE-07 approval workflow.
 */
class ProjectAmendmentRequiresApprovalException extends DomainException
{
    public static function forProject(int $learnerProjectId): self
    {
        return new self(
            "Amending a verified project (#{$learnerProjectId}) requires an approved CORE-07 request.",
            ['learner_project_id' => $learnerProjectId],
        );
    }

    public function errorCode(): string
    {
        return 'PROJECT_AMENDMENT_REQUIRES_APPROVAL';
    }
}
