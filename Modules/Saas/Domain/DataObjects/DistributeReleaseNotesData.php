<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class DistributeReleaseNotesData
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array{school_id: int, admin_user_id: int, email: string}>  $candidateRecipients  every school administrator who WOULD receive this if their school has the module — filtered down to only those whose school actually has `$moduleCode` enabled
     */
    public function __construct(
        public string $moduleCode,
        public string $notificationKey,
        public array $context,
        public array $candidateRecipients,
    ) {}
}
