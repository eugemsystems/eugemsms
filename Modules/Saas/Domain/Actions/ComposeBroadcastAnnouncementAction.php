<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\DataObjects\ComposeBroadcastAnnouncementData;
use Modules\Saas\Models\BroadcastAnnouncement;

/**
 * ACT-ComposeBroadcastAnnouncement (Book J SAA-02 §3/§4/BR-SAA-02-006).
 */
final class ComposeBroadcastAnnouncementAction extends Action
{
    public function execute(ComposeBroadcastAnnouncementData $data): BroadcastAnnouncement
    {
        if (trim($data->title) === '' || mb_strlen($data->title) > 200 || trim($data->body) === '' || mb_strlen($data->body) > 5000) {
            throw new InvalidArgumentException('An announcement needs a title (up to 200 characters) and a body (up to 5,000).');
        }

        if (! in_array($data->severity, ['info', 'maintenance', 'incident'], true)) {
            throw new InvalidArgumentException("[{$data->severity}] is not an announcement severity.");
        }

        // Targeting is explicit (BR-SAA-02-006): `null` means every tenant and an empty
        // list means no one — an empty selection is never silently widened to "all".
        $targets = $data->targetTenantIds === null ? null : array_values(array_unique($data->targetTenantIds));

        if ($targets !== null && ($targets === [] || Tenant::query()->whereIn('id', $targets)->count() !== count($targets))) {
            throw new InvalidArgumentException('Choose at least one existing tenant, or address every tenant explicitly.');
        }

        if ($data->endsAt !== null && $data->endsAt->lessThanOrEqualTo($data->startsAt ?? Carbon::now())) {
            throw new InvalidArgumentException('An announcement must end after it starts.');
        }

        return $this->transaction(fn (): BroadcastAnnouncement => BroadcastAnnouncement::create([
            'title' => $data->title,
            'body' => $data->body,
            'severity' => $data->severity,
            'target_tenant_ids' => $targets,
            'starts_at' => $data->startsAt ?? Carbon::now(),
            'ends_at' => $data->endsAt,
            'posted_by' => $data->postedBy,
        ]));
    }
}
