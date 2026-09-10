<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\Actions\Notifications\CreateOptOutAction;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\Actions\Notifications\SetNotificationBudgetAction;
use Modules\Core\Domain\Actions\Notifications\SetNotificationPreferenceAction;
use Modules\Core\Domain\Contracts\Notifications\NotificationChannelDriver;
use Modules\Core\Domain\DataObjects\Notifications\ChannelSendResult;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\DataObjects\Notifications\CreateOptOutData;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\DataObjects\Notifications\SetNotificationBudgetData;
use Modules\Core\Domain\DataObjects\Notifications\SetNotificationPreferenceData;
use Modules\Core\Domain\Exceptions\MissingNotificationVariableException;
use Modules\Core\Domain\Exceptions\UnregisteredNotificationKeyException;
use Modules\Core\Domain\Registry\NotificationChannelDriverRegistry;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Models\School;

beforeEach(function (): void {
    NotificationKeyRegistry::clear();
    NotificationChannelDriverRegistry::clear();

    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'fee.payment_received',
        variables: ['guardian.name', 'learner.first_name'],
        defaultChannels: ['sms', 'email'],
        defaultAudience: 'fee_responsible',
    ));

    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'safety.missing_boarder',
        variables: ['learner.first_name'],
        defaultChannels: ['sms'],
        defaultAudience: 'all_guardians',
        isUrgent: true,
        isTransactional: true,
    ));
});

function makeTemplate(string $key = 'fee.payment_received', string $channel = 'sms', string $body = '{{ guardian.name }}, payment received for {{ learner.first_name }}.'): void
{
    app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: $key,
        channel: $channel,
        body: $body,
    ));
}

it('throws for an unregistered notification key (BR-CORE-09-002)', function (): void {
    NotificationKeyRegistry::clear();

    app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: School::factory()->create()->id,
        notificationKey: 'unknown.key',
        recipientType: 'user',
        addresses: ['sms' => '0771234567'],
    ));
})->throws(UnregisteredNotificationKeyException::class);

it('fails loudly when a referenced variable is missing from the context (BR-CORE-09-003/AC-CORE-09-002)', function (): void {
    $school = School::factory()->create();
    makeTemplate();

    app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'fee.payment_received',
        recipientType: 'user',
        addresses: ['sms' => '0771234567'],
        context: ['guardian' => ['name' => 'Mrs Moyo']],
    ));
})->throws(MissingNotificationVariableException::class);

it('sends a notification and mirrors it to the in-app inbox (BR-CORE-09-012)', function (): void {
    $school = School::factory()->create();
    makeTemplate();

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'fee.payment_received',
        recipientType: 'user',
        recipientId: 1,
        addresses: ['sms' => '0771234567'],
        context: ['guardian' => ['name' => 'Mrs Moyo'], 'learner' => ['first_name' => 'Tendai']],
    ));

    expect($notification->status)->toBe('sent')
        ->and($notification->recipient_address)->toBe('+263771234567')
        ->and($notification->body)->toBe('Mrs Moyo, payment received for Tendai.');

    $this->assertDatabaseHas('notifications', [
        'school_id' => $school->id,
        'channel' => 'in_app',
        'related_id' => $notification->related_id,
        'status' => 'delivered',
    ]);
});

it('normalises and validates the recipient address, suppressing an invalid one (BR-CORE-09-013)', function (): void {
    $school = School::factory()->create();
    makeTemplate('fee.payment_received', 'email');

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'fee.payment_received',
        recipientType: 'user',
        addresses: ['email' => 'not-an-email-address'],
        context: ['guardian' => ['name' => 'X'], 'learner' => ['first_name' => 'Y']],
        channel: 'email',
    ));

    expect($notification->status)->toBe('suppressed')
        ->and($notification->error_code)->toBe('invalid_address');
});

it('suppresses a notification to an opted-out address', function (): void {
    $school = School::factory()->create();
    makeTemplate();
    app(CreateOptOutAction::class)->execute(new CreateOptOutData($school->id, '+263771234567', 'sms'));

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'fee.payment_received',
        recipientType: 'user',
        addresses: ['sms' => '0771234567'],
        context: ['guardian' => ['name' => 'X'], 'learner' => ['first_name' => 'Y']],
    ));

    expect($notification->status)->toBe('suppressed')
        ->and($notification->error_code)->toBe('opted_out');
});

it('does not suppress a transactional/safety notice for an opted-out address (BR-CORE-09-006)', function (): void {
    $school = School::factory()->create();
    makeTemplate('safety.missing_boarder', 'sms', 'Missing: {{ learner.first_name }}.');
    app(CreateOptOutAction::class)->execute(new CreateOptOutData($school->id, '+263771234567', 'sms'));

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'safety.missing_boarder',
        recipientType: 'user',
        addresses: ['sms' => '0771234567'],
        context: ['learner' => ['first_name' => 'Tendai']],
    ));

    expect($notification->status)->toBe('sent');
});

it('suppresses a notification a preference has disabled for this key and channel', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    makeTemplate();
    app(SetNotificationPreferenceAction::class)->execute(new SetNotificationPreferenceData($school->id, $user->id, 'sms', false, 'fee.payment_received'));

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'fee.payment_received',
        recipientType: 'user',
        recipientId: $user->id,
        addresses: ['sms' => '0771234567'],
        context: ['guardian' => ['name' => 'X'], 'learner' => ['first_name' => 'Y']],
    ));

    expect($notification->status)->toBe('suppressed')
        ->and($notification->error_code)->toBe('preference_disabled');
});

it('suppresses a duplicate notification within the dedupe window (BR-CORE-09-009)', function (): void {
    $school = School::factory()->create();
    makeTemplate();

    $data = new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'fee.payment_received',
        recipientType: 'user',
        recipientId: 1,
        addresses: ['sms' => '0771234567'],
        context: ['guardian' => ['name' => 'X'], 'learner' => ['first_name' => 'Y']],
        relatedType: 'receipt',
        relatedId: 99,
    );

    $first = app(DispatchNotificationAction::class)->execute($data);
    $second = app(DispatchNotificationAction::class)->execute($data);

    expect($first->status)->toBe('sent')
        ->and($second->status)->toBe('suppressed')
        ->and($second->error_code)->toBe('duplicate');
});

it('suppresses a non-urgent send once the hard-stop budget cap is reached, alerting the administrator (BR-CORE-09-008/AC-CORE-09-003)', function (): void {
    $school = School::factory()->create();
    makeTemplate();
    app(SetNotificationBudgetAction::class)->execute(new SetNotificationBudgetData(
        schoolId: $school->id,
        periodMonth: now()->format('Y-m'),
        channel: 'sms',
        currency: 'USD',
        capMinor: 100,
        isHardStop: true,
    ));

    NotificationChannelDriverRegistry::register(new class implements NotificationChannelDriver
    {
        public function channel(): string
        {
            return 'sms';
        }

        public function send(string $address, ?string $subject, string $body): ChannelSendResult
        {
            return new ChannelSendResult(succeeded: true, costMinor: 150);
        }
    });

    // First send pushes spend past the cap...
    app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'fee.payment_received',
        recipientType: 'user',
        addresses: ['sms' => '0771234567'],
        context: ['guardian' => ['name' => 'X'], 'learner' => ['first_name' => 'Y']],
        relatedType: 'receipt',
        relatedId: 1,
    ));

    // ...so a second, non-urgent send is suppressed.
    $second = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'fee.payment_received',
        recipientType: 'user',
        addresses: ['sms' => '0771234567'],
        context: ['guardian' => ['name' => 'X'], 'learner' => ['first_name' => 'Y']],
        relatedType: 'receipt',
        relatedId: 2,
    ));

    expect($second->status)->toBe('suppressed')
        ->and($second->error_code)->toBe('budget_cap_reached');

    makeTemplate('safety.missing_boarder', 'sms', 'Missing: {{ learner.first_name }}.');

    $urgent = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'safety.missing_boarder',
        recipientType: 'user',
        addresses: ['sms' => '0771234567'],
        context: ['learner' => ['first_name' => 'Tendai']],
    ));

    expect($urgent->status)->toBe('sent');
});

it('falls back to the next channel on a hard failure, recording both attempts (BR-CORE-09-004/AC-CORE-09-004)', function (): void {
    $school = School::factory()->create();
    makeTemplate('fee.payment_received', 'sms');
    makeTemplate('fee.payment_received', 'email');

    NotificationChannelDriverRegistry::register(new class implements NotificationChannelDriver
    {
        public function channel(): string
        {
            return 'sms';
        }

        public function send(string $address, ?string $subject, string $body): ChannelSendResult
        {
            return new ChannelSendResult(succeeded: false, errorCode: 'gateway_error', errorMessage: 'SMS gateway unreachable.');
        }
    });

    NotificationChannelDriverRegistry::register(new class implements NotificationChannelDriver
    {
        public function channel(): string
        {
            return 'email';
        }

        public function send(string $address, ?string $subject, string $body): ChannelSendResult
        {
            return new ChannelSendResult(succeeded: true, providerMessageId: 'email-1');
        }
    });

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $school->id,
        notificationKey: 'fee.payment_received',
        recipientType: 'user',
        addresses: ['sms' => '0771234567', 'email' => 'parent@example.com'],
        context: ['guardian' => ['name' => 'X'], 'learner' => ['first_name' => 'Y']],
        channel: 'sms',
    ));

    expect($notification->status)->toBe('sent')
        ->and($notification->channel)->toBe('email')
        ->and($notification->recipient_address)->toBe('parent@example.com')
        ->and($notification->attempt_count)->toBe(2);
});
