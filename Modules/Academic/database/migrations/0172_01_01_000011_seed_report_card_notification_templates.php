<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Models\NotificationTemplate;

/**
 * Book D ACA-05 §6/BR-ACA-05-018. System-default templates for the two
 * report-card notifications. They carry a prompt to open the portal, never an
 * attachment. Runs after `AcademicServiceProvider::boot()` has registered the
 * keys.
 */
return new class extends Migration
{
    private const TEMPLATES = [
        'academic.report_card_published' => ["{{ student.first_name }}'s report card is now available. Please open the parent portal to view it.", 'Report card available'],
        'academic.report_card_amended' => ["{{ student.first_name }}'s report card has been updated after a mark correction. Please open the parent portal to see the amended version.", 'Amended report card'],
    ];

    public function up(): void
    {
        foreach (self::TEMPLATES as $key => [$body, $subject]) {
            foreach (['sms' => null, 'email' => $subject] as $channel => $channelSubject) {
                if (NotificationTemplate::where('key', $key)->where('channel', $channel)->whereNull('school_id')->exists()) {
                    continue;
                }

                app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
                    key: $key,
                    channel: $channel,
                    body: $body,
                    subject: $channelSubject,
                ));
            }
        }
    }

    public function down(): void
    {
        NotificationTemplate::whereIn('key', array_keys(self::TEMPLATES))->whereNull('school_id')->delete();
    }
};
