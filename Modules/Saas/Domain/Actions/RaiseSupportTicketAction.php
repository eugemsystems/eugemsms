<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Saas\Domain\DataObjects\RaiseSupportTicketData;
use Modules\Saas\Models\SupportTicket;

/**
 * ACT-RaiseSupportTicket (Book J SAA-03 §2 ⭐/§4/BR-SAA-03-003
 * ⭐/BR-SAA-03-004 (AC-SAA-03-001)). Flows from a school's user to the
 * VENDOR — `Modules\Comms\Domain\Actions\RaiseComplaintAction`
 * (`COM-08`) is the school-facing mirror of this and the two never
 * share a queue or a table. `sla_due_at` is computed from the
 * ticket's own `priority` at intake, reusing `COM-08`'s "computed at
 * intake" SLA pattern (`CheckComplaintSlaAction`), not a second
 * implementation.
 */
final class RaiseSupportTicketAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(RaiseSupportTicketData $data): SupportTicket
    {
        $scope = new ScopeChain;
        $slaHours = (int) $this->settings->get("saas.support_sla_hours_{$data->priority}", $scope);

        return $this->transaction(fn (): SupportTicket => SupportTicket::create([
            'tenant_id' => $data->tenantId,
            'school_id' => $data->schoolId,
            'raised_by_user_id' => $data->raisedByUserId,
            'subject' => $data->subject,
            'description' => $data->description,
            'category' => $data->category,
            'priority' => $data->priority,
            'sla_due_at' => Carbon::now()->addHours($slaHours),
            'status' => 'open',
        ]));
    }
}
