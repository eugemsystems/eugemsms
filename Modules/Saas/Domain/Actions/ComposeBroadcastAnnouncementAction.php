<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\ComposeBroadcastAnnouncementData;
use Modules\Saas\Models\BroadcastAnnouncement;

/**
 * ACT-ComposeBroadcastAnnouncement (Book J SAA-02 §3/§4/BR-SAA-02-006).
 */
final class ComposeBroadcastAnnouncementAction extends Action
{
    public function execute(ComposeBroadcastAnnouncementData $data): BroadcastAnnouncement
    {
        return $this->transaction(fn (): BroadcastAnnouncement => BroadcastAnnouncement::create([
            'title' => $data->title,
            'body' => $data->body,
            'severity' => $data->severity,
            'target_tenant_ids' => $data->targetTenantIds,
            'starts_at' => $data->startsAt ?? Carbon::now(),
            'ends_at' => $data->endsAt,
            'posted_by' => $data->postedBy,
        ]));
    }
}
