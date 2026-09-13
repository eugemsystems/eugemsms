<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book B FIN-03 §4/BR-FIN-03-015. The system-default (`school_id`
 * null) SMS/email template for `finance.fee_reminder` — see Book D
 * ACA-04's own identical seed migration for why this runs safely after
 * `FinanceServiceProvider::boot()` has already registered the key.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sms', 'email'] as $channel) {
            if (NotificationTemplate::where('key', 'finance.fee_reminder')->where('channel', $channel)->whereNull('school_id')->exists()) {
                continue;
            }

            app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
                key: 'finance.fee_reminder',
                channel: $channel,
                body: 'Dear {{ guardian.name }}, invoice {{ invoice.number }} has an outstanding balance of {{ invoice.currency }} {{ invoice.balance }}, due {{ invoice.due_date }}. Please settle at your earliest convenience.',
                subject: $channel === 'email' ? 'Outstanding school fees' : null,
            ));
        }
    }

    public function down(): void
    {
        NotificationTemplate::where('key', 'finance.fee_reminder')->whereNull('school_id')->delete();
    }
};
