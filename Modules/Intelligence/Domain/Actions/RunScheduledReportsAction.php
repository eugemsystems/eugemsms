<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Intelligence\Models\CustomReportSchedule;
use Throwable;

/**
 * ACT-RunScheduledReports (Book J INT-01 §2/BR-INT-01-010). Meant to
 * run on a schedule against every due `CustomReportSchedule`,
 * mirroring `Modules\Comms\Domain\Actions\CheckComplaintSlaAction`'s
 * own "meant to run on a schedule" note.
 *
 * "Scheduled delivery follows exactly the recipient and channel
 * machinery of `CORE-09`; this module supplies the content, not a
 * second delivery mechanism" — this action supplies exactly that
 * content (the report's own name and row count) through the real
 * `DispatchNotificationAction`. Rendering an actual PDF/Excel/CSV
 * FILE attachment is a real, separate export-rendering concern this
 * pass does not build (`CORE-09`'s own channel drivers are text-only,
 * per COM-01's own docblocks) — the notification is the delivery
 * SIGNAL a recipient acts on, not a file transport.
 */
final class RunScheduledReportsAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly RunSavedReportAction $runSavedReport,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(int $scheduleId): int
    {
        $schedule = CustomReportSchedule::with('report.creator')->findOrFail($scheduleId);

        if (! $schedule->is_active || $schedule->next_run_at === null || $schedule->next_run_at->isFuture()) {
            return 0;
        }

        $result = $this->runSavedReport->execute($schedule->report_id, $schedule->report->creator);
        $delivered = 0;

        foreach ($schedule->recipients as $recipient) {
            if ($this->deliver($schedule, $recipient, $result->rowCount)) {
                $delivered++;
            }
        }

        $this->transaction(function () use ($schedule): void {
            $schedule->update(['next_run_at' => $this->nextRunAfter($schedule)]);
        });

        return $delivered;
    }

    /**
     * @param  array{recipientType?: string, recipientId?: int, channel?: string}  $recipient
     */
    private function deliver(CustomReportSchedule $schedule, array $recipient, int $rowCount): bool
    {
        if (($recipient['recipientType'] ?? null) !== 'user' || ! isset($recipient['recipientId'])) {
            return false;
        }

        $user = User::find($recipient['recipientId']);

        if ($user === null) {
            return false;
        }

        $channel = $recipient['channel'] ?? 'email';

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $schedule->school_id,
                notificationKey: 'intelligence.scheduled_report_ready',
                recipientType: 'user',
                addresses: [$channel => (string) $user->email],
                context: ['report' => ['name' => $schedule->report->name], 'row_count' => $rowCount, 'format' => $schedule->format],
                recipientId: $user->id,
                channel: $channel,
            ));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function nextRunAfter(CustomReportSchedule $schedule): Carbon
    {
        return match ($schedule->frequency) {
            'daily' => Carbon::now()->addDay(),
            'weekly' => Carbon::now()->addWeek(),
            'monthly' => Carbon::now()->addMonth(),
            'termly' => Carbon::now()->addMonths(4),
            default => Carbon::now()->addDay(),
        };
    }
}
