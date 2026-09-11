<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Saas\Models\OnboardingChecklist;
use Throwable;

/**
 * ACT-ListStalledOnboardingChecklists (Book J SAA-03 §4/BR-SAA-03-001
 * (AC-SAA-03-003)). Meant to run on a schedule — the same "bookkeeping
 * real, wiring deferred" boundary this book set already draws (see
 * `Modules\Comms\Domain\Actions\CheckComplaintSlaAction`). "No
 * progress" is the most recent step `completed_at` across the
 * checklist, or `started_at` if nothing has been completed yet.
 */
final class ListStalledOnboardingChecklistsAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    /**
     * @return array<int, OnboardingChecklist>
     */
    public function execute(): array
    {
        $thresholdDays = (int) $this->settings->get('saas.onboarding_stall_threshold_days', new ScopeChain);
        $stalled = [];

        foreach (OnboardingChecklist::where('status', 'in_progress')->get() as $checklist) {
            $lastActivity = $this->lastActivityAt($checklist);

            if (Carbon::now()->diffInDays($lastActivity, absolute: true) < $thresholdDays) {
                continue;
            }

            $stalled[] = $checklist;
            $this->alert($checklist);
        }

        return $stalled;
    }

    private function lastActivityAt(OnboardingChecklist $checklist): CarbonInterface
    {
        $latest = $checklist->started_at;

        foreach ($checklist->steps as $step) {
            if ($step['completed_at'] === null) {
                continue;
            }

            $completedAt = Carbon::parse($step['completed_at']);

            if ($completedAt->greaterThan($latest)) {
                $latest = $completedAt;
            }
        }

        return $latest;
    }

    private function alert(OnboardingChecklist $checklist): void
    {
        if ($checklist->assigned_success_manager === null) {
            return;
        }

        $manager = User::find($checklist->assigned_success_manager);

        if ($manager === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $checklist->school_id,
                notificationKey: 'saas.onboarding_checklist_stalled',
                recipientType: 'user',
                addresses: ['email' => $manager->email],
                recipientId: $manager->id,
                relatedType: 'onboarding_checklist',
                relatedId: $checklist->id,
                dedupeWindowMinutes: 1440,
            ));
        } catch (Throwable) {
            // A notification-dispatch failure never blocks the stall check itself.
        }
    }
}
