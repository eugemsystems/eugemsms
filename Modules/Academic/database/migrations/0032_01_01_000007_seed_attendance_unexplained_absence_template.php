<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book D ACA-04 §7 ⭐/BR-ACA-04-006. The system-default (`school_id`
 * null) SMS/email template for `attendance.unexplained_absence` — a
 * school can override it per Book A CORE-09 §2's resolution order.
 * Runs after `AcademicServiceProvider::boot()` has already registered
 * the key (every provider boots before any migration runs), so
 * `CreateNotificationTemplateAction`'s own variable-registration check
 * passes.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sms', 'email'] as $channel) {
            if (NotificationTemplate::where('key', 'attendance.unexplained_absence')->where('channel', $channel)->whereNull('school_id')->exists()) {
                continue;
            }

            app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
                key: 'attendance.unexplained_absence',
                channel: $channel,
                body: '{{ guardian.name }}, {{ student.first_name }} {{ student.last_name }} was marked absent today ({{ date }}) with no reason recorded. Please contact the school if this is unexpected.',
                subject: $channel === 'email' ? 'Unexplained absence today' : null,
            ));
        }
    }

    public function down(): void
    {
        NotificationTemplate::where('key', 'attendance.unexplained_absence')->whereNull('school_id')->delete();
    }
};
