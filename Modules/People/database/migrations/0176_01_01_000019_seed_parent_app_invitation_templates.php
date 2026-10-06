<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book C PPL-03. System-default SMS and email invitations for a guardian given parent-app access.
 * Runs after `PeopleServiceProvider::boot()` has registered the `people.parent_app_invitation` key.
 */
return new class extends Migration
{
    private const KEY = 'people.parent_app_invitation';

    public function up(): void
    {
        $body = 'Hello {{ guardian.name }}, {{ school.name }} has given you access to the parent app. Install the app and sign in with {{ guardian.phone }} - we will send you a one-time code each time.';

        foreach (['sms' => null, 'email' => 'You can now use the {{ school.name }} parent app'] as $channel => $subject) {
            if (NotificationTemplate::where('key', self::KEY)->where('channel', $channel)->whereNull('school_id')->exists()) {
                continue;
            }

            app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(key: self::KEY, channel: $channel, body: $body, subject: $subject));
        }
    }

    public function down(): void
    {
        NotificationTemplate::where('key', self::KEY)->whereNull('school_id')->delete();
    }
};
