<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\Notice;
use Modules\Comms\Models\NoticeRead;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Throwable;

/**
 * ACT-EscalateUnreadUrgentNotice (Book I COM-06 §3/BR-COM-06-005).
 * Meant to run on a schedule against every published urgent notice,
 * mirroring how `Modules\Boarding\Domain\Actions\AdvanceEscalationLadderAction`
 * is described as scheduled — wiring that schedule entry is a
 * deployment step, not this action's concern.
 *
 * **Reading of "escalates a reminder through CORE-09"**: `DispatchNotificationAction`
 * requires one addressable recipient, not an unenumerated audience —
 * so this alerts the notice's OWN poster to the low read-rate (the
 * same "alert the assignee" shape `BR-COM-08-004` uses for an
 * approaching complaint SLA), rather than attempting to re-broadcast
 * to everyone who hasn't read it yet. "Once, not repeatedly" is
 * `DispatchNotificationAction`'s own dedupe (`relatedType`/`relatedId`
 * plus a window wide enough to span the notice's practical lifetime),
 * not a hand-rolled flag on the notice itself.
 *
 * Audience-size resolution is a documented simplification: for any
 * scope narrower than `whole_school`, this still counts the WHOLE
 * school's guardian+staff population as the denominator (an upper
 * bound) rather than resolving `section`/`level`/`class`/`house`
 * membership — the same honest boundary `CalendarAudienceFilter`
 * draws for those same four scope values.
 */
final class EscalateUnreadUrgentNoticeAction extends Action
{
    private const int DEDUPE_WINDOW_MINUTES = 10080;

    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(int $noticeId): bool
    {
        $notice = Notice::findOrFail($noticeId);

        if ($notice->priority !== 'urgent' || $notice->status !== 'published') {
            return false;
        }

        $scope = new ScopeChain(schoolId: $notice->school_id);
        $delayMinutes = (int) $this->settings->get('comms.notice_escalation_delay_minutes', $scope);

        if ($notice->publish_at->greaterThan(Carbon::now()->subMinutes($delayMinutes))) {
            return false;
        }

        $audienceSize = Guardian::where('school_id', $notice->school_id)->whereNotNull('user_id')->count()
            + Staff::where('school_id', $notice->school_id)->whereNotNull('user_id')->count();

        if ($audienceSize === 0) {
            return false;
        }

        $readCount = NoticeRead::where('notice_id', $notice->id)->count();
        $unreadPercent = (($audienceSize - $readCount) / $audienceSize) * 100;
        $thresholdPercent = (int) $this->settings->get('comms.notice_escalation_unread_threshold_percent', $scope);

        if ($unreadPercent < $thresholdPercent) {
            return false;
        }

        $poster = Staff::where('school_id', $notice->school_id)->where('user_id', $notice->posted_by)->first();

        if ($poster === null) {
            return false;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $notice->school_id,
                notificationKey: 'comms.notice_escalation',
                recipientType: 'staff',
                addresses: ['email' => (string) $poster->work_email],
                context: ['notice' => ['title' => $notice->title], 'unread_percent' => round($unreadPercent)],
                recipientId: $poster->user_id,
                relatedType: 'notice',
                relatedId: $notice->id,
                dedupeWindowMinutes: self::DEDUPE_WINDOW_MINUTES,
            ));
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
