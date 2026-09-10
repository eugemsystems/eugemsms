<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Intelligence\Domain\Registry\KpiRegistry;
use Modules\Intelligence\Models\ExecutiveDigest;
use Throwable;

/**
 * ACT-GenerateExecutiveDigest (Book J INT-02 §3 ⭐/BR-INT-02-004/005
 * (AC-INT-02-002)). Summarises EXCEPTIONS, not routine status — an
 * all-green day produces a short confirmation, never every KPI value
 * re-listed. Delivery goes through the real `DispatchNotificationAction`
 * (`CORE-09`) exclusively; "respects the head's own channel
 * preference" is `DispatchNotificationAction`'s own preference-check
 * suppressing a disabled channel — this action does not build a
 * second best-channel picker, it supplies one candidate channel and
 * lets the existing pipeline decide.
 */
final class GenerateExecutiveDigestAction extends Action
{
    public function __construct(
        private readonly GetKpiValueAction $getKpiValue,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(int $schoolId, int $academicYearId, User $recipient, string $channel = 'email'): ExecutiveDigest
    {
        $exceptions = [];

        foreach (KpiRegistry::all() as $kpi) {
            $result = $this->getKpiValue->execute($kpi->key, $schoolId, $academicYearId);

            if ($result->status !== 'green') {
                $exceptions[] = [
                    'key' => $result->key, 'label' => $result->label, 'status' => $result->status,
                    'current_value' => $result->currentValue, 'target_value' => $result->targetValue,
                ];
            }
        }

        $summary = ['all_green' => $exceptions === [], 'exceptions' => $exceptions];

        $digest = $this->transaction(fn (): ExecutiveDigest => ExecutiveDigest::updateOrCreate(
            ['school_id' => $schoolId, 'recipient_user_id' => $recipient->id, 'digest_date' => Carbon::today()],
            ['content_summary' => $summary, 'delivered_via' => $channel],
        ));

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $schoolId,
                notificationKey: 'intelligence.executive_digest',
                recipientType: 'user',
                addresses: [$channel => (string) $recipient->email],
                context: ['all_green' => $summary['all_green'], 'exception_count' => count($exceptions)],
                recipientId: $recipient->id,
                channel: $channel,
                relatedType: 'executive_digest',
                relatedId: $digest->id,
            ));

            $this->transaction(fn () => $digest->update(['sent_at' => Carbon::now()]));
        } catch (Throwable) {
            // A notification-dispatch failure never blocks the digest record itself.
        }

        return $digest->fresh();
    }
}
