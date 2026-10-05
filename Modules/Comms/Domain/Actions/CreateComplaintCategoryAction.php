<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\ComplaintCategory;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateComplaintCategory (Book I COM-08 §2). The backend pass had
 * the `complaint_categories` table and a factory but no way to create a
 * category, so no complaint could ever be raised. The SLA is fixed at
 * category level and copied onto each complaint's `sla_due_at` at
 * intake (BR-COM-08-003). A category flagged `isSafeguardingTrigger`
 * routes every complaint raised under it to BRD-08.
 */
final class CreateComplaintCategoryAction extends Action
{
    public function execute(int $schoolId, string $code, string $name, int $slaHours = 72, bool $isSafeguardingTrigger = false): ComplaintCategory
    {
        return $this->transaction(fn (): ComplaintCategory => ComplaintCategory::create([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $name,
            'sla_hours' => $slaHours,
            'is_safeguarding_trigger' => $isSafeguardingTrigger,
        ]));
    }
}
