<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book K ACA-08 §4/BR-ACA-08-009. The system-default `in_app` template
 * for `lms.non_submission_reminder` — the one-tap chase a teacher
 * fires from the non-submission list. Runs after
 * `AcademicServiceProvider::boot()` has already registered the key
 * (every provider boots before any migration runs).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (NotificationTemplate::where('key', 'lms.non_submission_reminder')->where('channel', 'in_app')->whereNull('school_id')->exists()) {
            return;
        }

        app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
            key: 'lms.non_submission_reminder',
            channel: 'in_app',
            body: 'Reminder: "{{ assignment.title }}" was due {{ assignment.due_at }}. Please submit as soon as you can.',
            subject: null,
        ));
    }

    public function down(): void
    {
        NotificationTemplate::where('key', 'lms.non_submission_reminder')->whereNull('school_id')->delete();
    }
};
