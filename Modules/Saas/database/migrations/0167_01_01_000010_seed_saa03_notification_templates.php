<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book J SAA-03 §4/BR-SAA-03-001/004/009. System-default (`school_id`
 * null) templates for every `saas.*` notification key
 * `SaasServiceProvider::registerNotificationKeys()` registers — see
 * Academic's `0032_01_01_000007_seed_attendance_unexplained_absence_template.php`
 * for why re-seeding from a migration after provider boot is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            ['key' => 'saas.onboarding_checklist_stalled', 'body' => 'An onboarding checklist you are assigned to has had no progress for longer than expected. Please check in with the school.', 'subject' => 'Onboarding checklist stalled'],
            ['key' => 'saas.support_ticket_sla_breached', 'body' => 'A support ticket assigned to you has breached its SLA. Please respond as soon as possible.', 'subject' => 'Support ticket SLA breached'],
            ['key' => 'saas.support_ticket_sla_approaching', 'body' => 'A support ticket assigned to you is approaching its SLA deadline.', 'subject' => 'Support ticket SLA approaching'],
            ['key' => 'saas.release_notes', 'body' => 'A new release of {{ module_name }} (version {{ version }}) is available: {{ summary }}', 'subject' => 'Release notes: {{ module_name }} {{ version }}'],
        ];

        foreach ($templates as $template) {
            if (NotificationTemplate::where('key', $template['key'])->where('channel', 'email')->whereNull('school_id')->exists()) {
                continue;
            }

            app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
                key: $template['key'],
                channel: 'email',
                body: $template['body'],
                subject: $template['subject'],
            ));
        }
    }

    public function down(): void
    {
        NotificationTemplate::whereIn('key', [
            'saas.onboarding_checklist_stalled',
            'saas.support_ticket_sla_breached',
            'saas.support_ticket_sla_approaching',
            'saas.release_notes',
        ])->whereNull('school_id')->delete();
    }
};
