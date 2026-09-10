<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Comms\Domain\Actions\CheckGatewayHealthAction;
use Modules\Comms\Domain\Actions\IngestMessagingWebhookAction;
use Modules\Comms\Domain\Actions\ReconcileGatewayCostAction;
use Modules\Comms\Domain\Actions\RegisterMessageGatewayAction;
use Modules\Comms\Domain\Actions\UpdateWhatsAppQualityRatingAction;
use Modules\Comms\Domain\DataObjects\IngestMessagingWebhookData;
use Modules\Comms\Domain\DataObjects\ReconcileGatewayCostData;
use Modules\Comms\Domain\DataObjects\RegisterMessageGatewayData;
use Modules\Comms\Domain\Events\WhatsAppQualityDegraded;
use Modules\Comms\Models\MessageConversation;
use Modules\Comms\Models\MessageSegment;
use Modules\Comms\Models\MessagingGatewayWebhook;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Comms\Models\WhatsAppTemplate;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;

/**
 * @return array<string, mixed>
 */
function com01Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();

    NotificationKeyRegistry::clear();
    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'test.notice',
        variables: ['name'],
        defaultChannels: ['whatsapp', 'sms', 'email'],
        defaultAudience: 'guardian',
    ));

    foreach (['sms', 'whatsapp', 'email'] as $channel) {
        app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
            key: 'test.notice', channel: $channel, body: 'Dear {{ name }}, this is a test.',
        ));
    }

    $smsGateway = app(RegisterMessageGatewayAction::class)->execute(new RegisterMessageGatewayData(
        schoolId: $school->id, channel: 'sms', driver: 'bulksms_zw', name: 'BulkSMS',
        credentials: 'secret-key', createdByUserId: $user->id,
    ));

    $waGateway = app(RegisterMessageGatewayAction::class)->execute(new RegisterMessageGatewayData(
        schoolId: $school->id, channel: 'whatsapp', driver: 'whatsapp_cloud_api', name: 'WA Cloud API',
        credentials: 'secret-token', createdByUserId: $user->id,
    ));

    $waba = WhatsAppBusinessAccount::factory()->create(['school_id' => $school->id, 'gateway_id' => $waGateway->id]);

    app(RegisterMessageGatewayAction::class)->execute(new RegisterMessageGatewayData(
        schoolId: $school->id, channel: 'email', driver: 'smtp', name: 'SMTP',
        credentials: 'secret-pass', createdByUserId: $user->id,
    ));

    return compact('school', 'user', 'smsGateway', 'waGateway', 'waba');
}

it('encrypts gateway credentials at rest (BR-COM-01-001)', function (): void {
    $f = com01Fixture();

    $raw = DB::table('message_gateways')->where('id', $f['smsGateway']->id)->value('credentials');

    expect($raw)->not->toBe('secret-key')
        ->and($f['smsGateway']->fresh()->credentials)->toBe('secret-key');
});

it('refuses free-form WhatsApp outside the session window and falls back to the next channel (AC-COM-01-001/002, BR-COM-01-003/005)', function (): void {
    $f = com01Fixture();

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $f['school']->id, notificationKey: 'test.notice', recipientType: 'guardian',
        addresses: ['whatsapp' => '0771234567', 'sms' => '0771234567'],
        context: ['name' => 'Mrs Moyo'],
    ));

    // No open session and no approved template exist yet — whatsapp is
    // refused, sms (the next configured channel) succeeds instead.
    expect($notification->channel)->toBe('sms')
        ->and($notification->status)->toBe('sent');
});

it('sends free-form WhatsApp inside an open session window', function (): void {
    $f = com01Fixture();
    MessageConversation::factory()->create([
        'school_id' => $f['school']->id, 'waba_id' => $f['waba']->id, 'contact_phone' => '+263771234567',
    ]);

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $f['school']->id, notificationKey: 'test.notice', recipientType: 'guardian',
        addresses: ['whatsapp' => '0771234567'], channel: 'whatsapp',
        context: ['name' => 'Mrs Moyo'],
    ));

    expect($notification->channel)->toBe('whatsapp')
        ->and($notification->status)->toBe('sent');
});

it('sends via an approved template outside the session window', function (): void {
    $f = com01Fixture();
    WhatsAppTemplate::factory()->create([
        'school_id' => $f['school']->id, 'waba_id' => $f['waba']->id, 'review_status' => 'approved',
    ]);

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $f['school']->id, notificationKey: 'test.notice', recipientType: 'guardian',
        addresses: ['whatsapp' => '0771234567'], channel: 'whatsapp',
        context: ['name' => 'Mrs Moyo'],
    ));

    expect($notification->channel)->toBe('whatsapp')
        ->and($notification->status)->toBe('sent');
});

it('normalises curly quotes and em-dashes before calculating segments (AC-COM-01-003, BR-COM-01-009)', function (): void {
    $f = com01Fixture();
    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'test.diacritic', variables: ['name'], defaultChannels: ['sms'], defaultAudience: 'guardian',
    ));
    app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: 'test.diacritic', channel: 'sms',
        body: "Dear Mr Moyo \u{2014} {{ name }}\u{2019}s balance is due.",
    ));

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $f['school']->id, notificationKey: 'test.diacritic', recipientType: 'guardian',
        addresses: ['sms' => '0771234567'], channel: 'sms',
        context: ['name' => 'Tinashe'],
    ));

    $notification = $notification->fresh();

    expect($notification->body)->not->toContain("\u{2014}")
        ->and($notification->body)->not->toContain("\u{2019}")
        ->and($notification->body)->toContain('Tinashe\'s');

    $segment = MessageSegment::where('notification_id', $notification->id)->firstOrFail();
    expect($segment->encoding)->toBe('gsm7')
        ->and($segment->segment_count)->toBe(1);
});

it('pauses non-critical WhatsApp sends and fires an event when quality rating reaches the threshold (AC-COM-01-004, BR-COM-01-007)', function (): void {
    Event::fake([WhatsAppQualityDegraded::class]);
    $f = com01Fixture();

    app(UpdateWhatsAppQualityRatingAction::class)->execute($f['waba']->id, 'red');

    Event::assertDispatched(WhatsAppQualityDegraded::class);
    expect($f['waba']->fresh()->quality_rating)->toBe('red');

    WhatsAppTemplate::factory()->create([
        'school_id' => $f['school']->id, 'waba_id' => $f['waba']->id, 'review_status' => 'approved',
    ]);

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $f['school']->id, notificationKey: 'test.notice', recipientType: 'guardian',
        addresses: ['whatsapp' => '0771234567', 'sms' => '0771234567'],
        context: ['name' => 'Mrs Moyo'],
    ));

    // whatsapp paused despite an approved template existing — falls
    // back to sms, the next configured channel.
    expect($notification->channel)->toBe('sms');
});

it('applies exactly one delivery status update when the same webhook is delivered five times (AC-COM-01-005, BR-COM-01-010)', function (): void {
    $f = com01Fixture();

    $notification = Notification::factory()->create([
        'school_id' => $f['school']->id, 'channel' => 'sms', 'status' => 'sent',
        'provider_message_id' => 'sms-abc123',
    ]);

    $payload = json_encode(['event' => 'delivered', 'message_id' => 'sms-abc123', 'status' => 'delivered']);

    for ($i = 0; $i < 5; $i++) {
        app(IngestMessagingWebhookAction::class)->execute(new IngestMessagingWebhookData(
            channel: 'sms', driver: $f['smsGateway']->driver,
            headers: ['X-Signature' => 'valid'], body: (string) $payload,
        ));
    }

    expect(MessagingGatewayWebhook::where('driver', $f['smsGateway']->driver)->count())->toBe(1);
    expect($notification->fresh()->status)->toBe('delivered')
        ->and($notification->fresh()->delivered_at)->not->toBeNull();
});

it('extends the WhatsApp session window on an inbound webhook message (BR-COM-01-004)', function (): void {
    $f = com01Fixture();

    $payload = json_encode(['event' => 'inbound_message', 'from' => '+263779999999', 'text' => 'Hello']);

    app(IngestMessagingWebhookAction::class)->execute(new IngestMessagingWebhookData(
        channel: 'whatsapp', driver: $f['waGateway']->driver,
        headers: ['X-Hub-Signature-256' => 'valid'], body: (string) $payload,
    ));

    $conversation = MessageConversation::where('school_id', $f['school']->id)
        ->where('waba_id', $f['waba']->id)
        ->where('contact_phone', '+263779999999')
        ->firstOrFail();

    expect($conversation->isSessionOpen())->toBeTrue();
});

it('excludes a gateway marked down from failover routing (BR-COM-01-002/011)', function (): void {
    $f = com01Fixture();
    $f['smsGateway']->update(['health_status' => 'down']);

    $notification = app(DispatchNotificationAction::class)->execute(new DispatchNotificationData(
        schoolId: $f['school']->id, notificationKey: 'test.notice', recipientType: 'guardian',
        addresses: ['sms' => '0771234567'], channel: 'sms',
        context: ['name' => 'Mrs Moyo'],
    ));

    expect($notification->status)->toBe('failed')
        ->and($notification->error_code)->toBe('no_gateway_configured');

    app(CheckGatewayHealthAction::class)->execute($f['school']->id);
    expect($f['smsGateway']->fresh()->health_status)->toBe('up');
});

it('flags a cost reconciliation variance beyond tolerance rather than absorbing it (AC-COM-01-006, BR-COM-01-012)', function (): void {
    $f = com01Fixture();
    Notification::factory()->create(['school_id' => $f['school']->id, 'channel' => 'sms', 'cost_minor' => 41200]);

    $reconciliation = app(ReconcileGatewayCostAction::class)->execute(new ReconcileGatewayCostData(
        schoolId: $f['school']->id, gatewayId: $f['smsGateway']->id,
        periodMonth: now()->format('Y-m'), providerInvoicedMinor: 46000,
    ));

    expect($reconciliation->status)->toBe('variance')
        ->and($reconciliation->variance_minor)->toBe(4800);
});
