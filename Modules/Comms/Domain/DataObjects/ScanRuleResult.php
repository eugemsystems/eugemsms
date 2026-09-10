<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class ScanRuleResult
{
    /**
     * @param  array<int, array{subject_type: string, subject_id: int, context: array<string, mixed>}>  $previewMatches  populated only in preview/dry-run mode (BR-COM-02-012)
     */
    public function __construct(
        public int $recordsScanned,
        public int $recordsMatched,
        public int $notificationsDispatched,
        public array $previewMatches = [],
    ) {}
}
