<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\Actions\Notifications\MarkNotificationReadAction;
use Modules\Core\Domain\Actions\Notifications\RecordDeliveryStatusAction;
use Modules\Core\Domain\Actions\Notifications\SetNotificationBudgetAction;
use Modules\Core\Domain\Actions\Notifications\SetNotificationPreferenceAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\DataObjects\Notifications\RecordDeliveryStatusData;
use Modules\Core\Domain\DataObjects\Notifications\SetNotificationBudgetData;
use Modules\Core\Domain\DataObjects\Notifications\SetNotificationPreferenceData;
use Modules\Core\Domain\Exceptions\UnknownTemplateVariableException;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Models\Notification;
use Modules\Core\Models\NotificationOptOut;
use Modules\Core\Models\School;

beforeEach(function (): void {
    NotificationKeyRegistry::clear();
    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'fee.payment_received',
        variables: ['guardian.name'],
        defaultChannels: ['sms'],
        defaultAudience: 'fee_responsible',
    ));
});

it('rejects a template referencing a variable not declared for its key', function (): void {
    app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: 'fee.payment_received',
        channel: 'sms',
        body: '{{ guardian.name }}, balance is {{ invoice.balance }}.',
    ));
})->throws(UnknownTemplateVariableException::class);

it('accepts a template whose variables are all declared', function (): void {
    $template = app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: 'fee.payment_received',
        channel: 'sms',
        body: 'Dear {{ guardian.name }}, thank you.',
    ));

    expect($template->variables)->toBe(['guardian.name']);
});

it('records a delivered status from a provider webhook (BR-CORE-09-010)', function (): void {
    $notification = Notification::factory()->create(['status' => 'sent']);

    $updated = app(RecordDeliveryStatusAction::class)->execute(new RecordDeliveryStatusData($notification->id, 'delivered'));

    expect($updated->status)->toBe('delivered')
        ->and($updated->delivered_at)->not->toBeNull();
});

it('auto opts-out an address on a hard bounce (BR-CORE-09-011)', function (): void {
    $notification = Notification::factory()->create(['status' => 'sent', 'recipient_address' => '+263771234567', 'channel' => 'sms']);

    app(RecordDeliveryStatusAction::class)->execute(new RecordDeliveryStatusData(
        $notification->id,
        'bounced',
        errorCode: 'invalid_number',
        isHardBounce: true,
    ));

    $optOut = NotificationOptOut::where('school_id', $notification->school_id)->where('address', '+263771234567')->where('channel', 'sms')->sole();
    expect($optOut->reason)->toBe('invalid');
});

it('marks a notification read exactly once', function (): void {
    $notification = Notification::factory()->create(['channel' => 'in_app', 'status' => 'delivered']);

    $read = app(MarkNotificationReadAction::class)->execute($notification);
    $readAt = $read->read_at;

    $readAgain = app(MarkNotificationReadAction::class)->execute($read->fresh());

    expect($readAgain->read_at->equalTo($readAt))->toBeTrue();
});

it('sets and updates a notification preference', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    app(SetNotificationPreferenceAction::class)->execute(new SetNotificationPreferenceData($school->id, $user->id, 'sms', false, 'fee.payment_received'));
    $preference = app(SetNotificationPreferenceAction::class)->execute(new SetNotificationPreferenceData($school->id, $user->id, 'sms', true, 'fee.payment_received'));

    expect($preference->is_enabled)->toBeTrue();
    $this->assertDatabaseCount('notification_preferences', 1);
});

it('creates and updates a monthly notification budget', function (): void {
    $school = School::factory()->create();

    $budget = app(SetNotificationBudgetAction::class)->execute(new SetNotificationBudgetData(
        schoolId: $school->id,
        periodMonth: '2026-09',
        channel: 'sms',
        currency: 'USD',
        capMinor: 5000,
    ));

    expect($budget->hasReachedCap())->toBeFalse();

    $budget->increment('spent_minor', 5000);
    expect($budget->fresh()->hasReachedCap())->toBeTrue();
});
