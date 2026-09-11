<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book K ACA-10 §4/BR-ACA-10-004. The system-default `in_app` template
 * for `library.overdue_reminder`, fired before any fine is charged.
 * Runs after `AcademicServiceProvider::boot()` has already registered
 * the key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (NotificationTemplate::where('key', 'library.overdue_reminder')->where('channel', 'in_app')->whereNull('school_id')->exists()) {
            return;
        }

        app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
            key: 'library.overdue_reminder',
            channel: 'in_app',
            body: '"{{ item.title }}" was due back {{ loan.due_on }}. Please return it to the library as soon as possible.',
            subject: null,
        ));
    }

    public function down(): void
    {
        NotificationTemplate::where('key', 'library.overdue_reminder')->whereNull('school_id')->delete();
    }
};
