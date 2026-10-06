<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book I COM-06. System-default email template for a newsletter issue; runs after
 * `CommsServiceProvider::boot()` has registered the `comms.newsletter` key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (NotificationTemplate::where('key', 'comms.newsletter')->where('channel', 'email')->whereNull('school_id')->exists()) {
            return;
        }

        app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
            key: 'comms.newsletter',
            channel: 'email',
            body: '{{ newsletter.text }}',
            subject: '{{ newsletter.title }} — {{ newsletter.issue_number }}',
        ));
    }

    public function down(): void
    {
        NotificationTemplate::where('key', 'comms.newsletter')->whereNull('school_id')->delete();
    }
};
