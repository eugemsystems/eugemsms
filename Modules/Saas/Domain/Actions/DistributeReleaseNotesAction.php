<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Models\SchoolModule;
use Modules\Saas\Domain\DataObjects\DistributeReleaseNotesData;
use Throwable;

/**
 * ACT-DistributeReleaseNotes (Book J SAA-03 §4/BR-SAA-03-009
 * (AC-SAA-03-004)). Reuses `CORE-09`'s dispatch bus entirely; the one
 * thing this Action adds is the module-entitlement filter — a
 * candidate recipient whose school does not have `moduleCode` enabled
 * is silently dropped before dispatch, so a Foundation-tier tenant
 * never hears about a feature it cannot use.
 */
final class DistributeReleaseNotesAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(DistributeReleaseNotesData $data): int
    {
        $enabledSchoolIds = SchoolModule::withoutGlobalScopes()
            ->where('module_code', $data->moduleCode)
            ->where('is_enabled', true)
            ->pluck('school_id')
            ->all();

        $notified = 0;

        foreach ($data->candidateRecipients as $recipient) {
            if (! in_array($recipient['school_id'], $enabledSchoolIds, true)) {
                continue;
            }

            try {
                $this->dispatchNotification->execute(new DispatchNotificationData(
                    schoolId: $recipient['school_id'],
                    notificationKey: $data->notificationKey,
                    recipientType: 'user',
                    addresses: ['email' => $recipient['email']],
                    context: $data->context,
                    recipientId: $recipient['admin_user_id'],
                    relatedType: 'release_notes',
                    relatedId: null,
                    dedupeWindowMinutes: 10080,
                ));

                $notified++;
            } catch (Throwable) {
                // A single recipient's dispatch failure never blocks the rest of the distribution.
            }
        }

        return $notified;
    }
}
