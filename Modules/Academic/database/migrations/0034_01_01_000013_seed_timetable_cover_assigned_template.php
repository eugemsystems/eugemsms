<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book E ACA-03 §9/BR-ACA-03-019. See
 * `0032_01_01_000007_seed_attendance_unexplained_absence_template.php`
 * for why this runs safely after `AcademicServiceProvider::boot()`
 * has already registered the key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (NotificationTemplate::where('key', 'timetable.cover_assigned')->where('channel', 'email')->whereNull('school_id')->exists()) {
            return;
        }

        app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
            key: 'timetable.cover_assigned',
            channel: 'email',
            body: 'You are covering {{ class.name }} for {{ subject.name }} in {{ venue.name }}. Work set: {{ work_set }}.',
            subject: 'Cover lesson assigned',
        ));
    }

    public function down(): void
    {
        NotificationTemplate::where('key', 'timetable.cover_assigned')->whereNull('school_id')->delete();
    }
};
