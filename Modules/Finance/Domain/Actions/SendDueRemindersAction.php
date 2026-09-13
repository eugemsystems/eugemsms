<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\PaymentPlan;
use Modules\Finance\Models\ReminderSchedule;
use Modules\Finance\Models\ReminderSent;
use Modules\People\Models\Guardian;

/**
 * ACT-SendDueReminders (Book B FIN-03 §4/BR-FIN-03-015/016,
 * AC-FIN-03-005/006). Scheduled — for every active rung, every
 * still-owing invoice that has crossed that rung's `days_after_due`
 * and hasn't already received it (`reminders_sent`'s own
 * `UNIQUE(schedule_id, invoice_id)` is the actual duplicate guard;
 * this query's own `whereDoesntHave`-style check is the same guard
 * expressed as a filter, not a second mechanism). A student under an
 * active, non-breached payment plan is skipped entirely
 * (BR-FIN-03-016) — a plan covers a party's whole balance, not one
 * invoice, so the suppression is per-student, not per-invoice.
 * Dispatches through `CORE-09`'s existing notification bus — this
 * does not hand-roll a second send path.
 */
final class SendDueRemindersAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly DispatchNotificationAction $dispatch,
    ) {}

    public function execute(): int
    {
        $sent = 0;

        ReminderSchedule::withoutGlobalScopes()
            ->where('is_active', true)
            ->chunkById(200, function ($schedules) use (&$sent): void {
                foreach ($schedules as $schedule) {
                    $sent += $this->sendForSchedule($schedule);
                }
            });

        return $sent;
    }

    private function sendForSchedule(ReminderSchedule $schedule): int
    {
        $cutoffDate = Carbon::today()->subDays($schedule->days_after_due);
        $sentIds = ReminderSent::withoutGlobalScopes()->where('schedule_id', $schedule->id)->pluck('invoice_id');

        $suppressedStudentIds = PaymentPlan::withoutGlobalScopes()
            ->where('school_id', $schedule->school_id)
            ->where('status', 'active')
            ->pluck('student_id');

        $query = Invoice::withoutGlobalScopes()
            ->where('school_id', $schedule->school_id)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->where('balance_minor', '>=', $schedule->minimum_balance_minor)
            ->where('due_date', '<=', $cutoffDate->toDateString())
            ->whereNotIn('id', $sentIds)
            ->whereNotIn('student_id', $suppressedStudentIds);

        if ($schedule->currency !== null) {
            $query->where('currency', $schedule->currency);
        }

        $count = 0;

        $query->chunkById(200, function ($invoices) use ($schedule, &$count): void {
            foreach ($invoices as $invoice) {
                if ($this->notifyFor($schedule, $invoice)) {
                    $count++;
                }
            }
        });

        return $count;
    }

    private function notifyFor(ReminderSchedule $schedule, Invoice $invoice): bool
    {
        $guardian = $schedule->audience === 'fee_responsible'
            ? Guardian::withoutGlobalScopes()->whereHas('studentGuardians', fn ($q) => $q->where('student_id', $invoice->student_id)->where('is_fee_responsible', true))->first()
            : null;

        $addresses = [];

        if ($guardian?->primary_phone !== null) {
            $addresses['sms'] = $guardian->primary_phone;
        }

        if ($guardian?->email !== null) {
            $addresses['email'] = $guardian->email;
        }

        if ($addresses === []) {
            return false;
        }

        try {
            $this->dispatch->execute(new DispatchNotificationData(
                schoolId: $schedule->school_id,
                notificationKey: 'finance.fee_reminder',
                recipientType: 'guardian',
                addresses: $addresses,
                context: [
                    'guardian.name' => $guardian->displayName(),
                    'invoice.number' => $invoice->invoice_number,
                    'invoice.balance' => number_format($invoice->balance_minor / 100, 2),
                    'invoice.currency' => $invoice->currency,
                    'invoice.due_date' => $invoice->due_date->toDateString(),
                ],
                recipientId: $guardian?->id,
                relatedType: 'invoice',
                relatedId: $invoice->id,
                dedupeWindowMinutes: 1440,
            ));
        } catch (DomainException) {
            // Messaging never blocks the operation it attaches to (Book
            // A CORE-09's own rule) — no `reminders_sent` row means this
            // rung is retried on the next scheduled run rather than
            // silently marked as fired.
            return false;
        }

        ReminderSent::create([
            'school_id' => $schedule->school_id,
            'schedule_id' => $schedule->id,
            'invoice_id' => $invoice->id,
            'balance_at_send_minor' => $invoice->balance_minor,
            'sent_at' => Carbon::now(),
        ]);

        return true;
    }
}
