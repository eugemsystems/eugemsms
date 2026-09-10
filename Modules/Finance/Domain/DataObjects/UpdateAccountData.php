<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

/**
 * `code`, `account_type_id`, and `system_key` are deliberately absent —
 * they are never editable through this action (BR-FIN-01-019 for system
 * accounts; recoding any account is a chart restructure, not a field
 * edit, and out of this action's scope).
 */
final readonly class UpdateAccountData
{
    public function __construct(
        public int $accountId,
        public int $updatedByUserId,
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $isPostable = null,
        public ?bool $requiresCostCentre = null,
    ) {}
}
