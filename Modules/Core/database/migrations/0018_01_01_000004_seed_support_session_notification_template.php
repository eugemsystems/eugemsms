<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book J SAA-02. System-default email telling the administrator who granted support access that
 * vendor staff have just used it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (NotificationTemplate::where('key', 'core.support_session_opened')->where('channel', 'email')->whereNull('school_id')->exists()) {
            return;
        }

        app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
            key: 'core.support_session_opened',
            channel: 'email',
            body: '{{ support.operator }} from the support team has opened a read-only support session viewing the system as {{ support.user }}, under the access you granted for ticket {{ support.ticket }}. The session ends by {{ support.expires_at }} at the latest. If you did not expect this, withdraw access from Users > Support access straight away.',
            subject: 'Support session opened — {{ support.ticket }}',
        ));
    }

    public function down(): void
    {
        NotificationTemplate::where('key', 'core.support_session_opened')->whereNull('school_id')->delete();
    }
};
