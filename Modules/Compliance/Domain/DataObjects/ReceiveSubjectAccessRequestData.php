<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class ReceiveSubjectAccessRequestData
{
    public function __construct(
        public int $schoolId,
        public string $requestType,
        public string $subjectType,
        public ?int $subjectId,
        public string $requesterName,
        public string $scopeDescription,
        public ?string $requesterRelationship = null,
    ) {}
}
