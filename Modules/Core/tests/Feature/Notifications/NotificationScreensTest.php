<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Comms\Models\MessageGateway;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Notifications\RetryNotificationAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\DataObjects\Notifications\RetryNotificationData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Notifications\Budget;
use Modules\Core\Livewire\Notifications\Failures;
use Modules\Core\Livewire\Notifications\Log;
use Modules\Core\Livewire\Notifications\OptOuts;
use Modules\Core\Livewire\Notifications\TemplateEditor;
use Modules\Core\Livewire\Notifications\Templates;
use Modules\Core\Models\Notification;
use Modules\Core\Models\NotificationBudget;
use Modules\Core\Models\NotificationOptOut;
use Modules\Core\Models\NotificationTemplate;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;

function loadNotificationRoutesForTest(): void
{
    if (! Route::has('notifications.log')) {
        require base_path('Modules/Core/routes/notifications.php');
    }
}

function grantNotificationPermissions(User $user, School $school, string ...$permissions): void
{
    loadNotificationRoutesForTest();

    $grants = array_map(function (string $permission): PermissionGrantData {
        $model = Permission::firstOrCreate(
            ['name' => $permission],
            ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'notification', 'action' => last(explode('.', $permission))],
        );

        return new PermissionGrantData($model->id, PermissionScope::School);
    }, $permissions);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: $grants,
    ));
}

function adminForNotificationTest(School $school): User
{
    $admin = User::factory()->create();
    $admin->schools()->attach($school, ['status' => 'active']);

    return $admin;
}

beforeEach(function (): void {
    NotificationKeyRegistry::clear();
});

it('lists notifications and refuses without core.notification.view', function (): void {
    $school = School::factory()->create();
    $admin = adminForNotificationTest($school);

    Livewire::actingAs($admin)->test(Log::class, ['school' => $school])->assertForbidden();

    grantNotificationPermissions($admin, $school, 'core.notification.view');
    Notification::factory()->create(['school_id' => $school->id, 'notification_key' => 'fee.payment_received']);

    Livewire::actingAs($admin)
        ->test(Log::class, ['school' => $school])
        ->assertSee('fee.payment_received');
});

it('lists failed notifications and retries one', function (): void {
    // The Comms module (Book I COM-01) registers a real
    // `FakeSmsGatewayDriver` for the 'sms' channel, replacing the
    // always-succeeding `NullNotificationChannelDriver` — it only
    // succeeds when the school has an active `MessageGateway` row.
    $school = School::factory()->create();
    $admin = adminForNotificationTest($school);
    grantNotificationPermissions($admin, $school, 'core.notification.view');
    MessageGateway::factory()->create(['school_id' => $school->id]);

    $failed = Notification::factory()->create(['school_id' => $school->id, 'status' => 'failed', 'channel' => 'sms']);

    Livewire::actingAs($admin)
        ->test(Failures::class, ['school' => $school])
        ->assertSee($failed->recipient_address)
        ->call('retry', $failed->id)
        ->assertDispatched('toast');

    expect($failed->fresh()->status)->toBe('sent');
});

it('retries only the selected failures in a batch', function (): void {
    $school = School::factory()->create();
    $admin = adminForNotificationTest($school);
    grantNotificationPermissions($admin, $school, 'core.notification.view');
    MessageGateway::factory()->create(['school_id' => $school->id]);

    $failedA = Notification::factory()->create(['school_id' => $school->id, 'status' => 'failed', 'channel' => 'sms']);
    $failedB = Notification::factory()->create(['school_id' => $school->id, 'status' => 'failed', 'channel' => 'sms']);
    $stillPending = Notification::factory()->create(['school_id' => $school->id, 'status' => 'queued']);

    Livewire::actingAs($admin)
        ->test(Failures::class, ['school' => $school])
        ->set('selected', [$failedA->id, $failedB->id])
        ->call('retrySelected')
        ->assertDispatched('toast');

    expect($failedA->fresh()->status)->toBe('sent')
        ->and($failedB->fresh()->status)->toBe('sent')
        ->and($stillPending->fresh()->status)->toBe('queued');
});

it('throws when retrying a notification that is not failed', function (): void {
    $school = School::factory()->create();
    $sent = Notification::factory()->create(['school_id' => $school->id, 'status' => 'sent']);

    app(RetryNotificationAction::class)->execute(new RetryNotificationData($sent->id));
})->throws(DomainException::class);

it('lists school and system notification templates', function (): void {
    $school = School::factory()->create();
    $admin = adminForNotificationTest($school);
    grantNotificationPermissions($admin, $school, 'core.notification.manage_templates');

    NotificationTemplate::factory()->create(['school_id' => $school->id, 'key' => 'fee.school_specific']);
    NotificationTemplate::factory()->create(['school_id' => null, 'key' => 'fee.system_default']);

    Livewire::actingAs($admin)
        ->test(Templates::class, ['school' => $school])
        ->assertSee('fee.school_specific')
        ->assertSee('fee.system_default');
});

it('creates a notification template for a registered key', function (): void {
    $school = School::factory()->create();
    $admin = adminForNotificationTest($school);
    grantNotificationPermissions($admin, $school, 'core.notification.manage_templates');

    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'fee.payment_received',
        variables: ['guardian.name'],
        defaultChannels: ['sms'],
        defaultAudience: 'fee_responsible',
    ));

    Livewire::actingAs($admin)
        ->test(TemplateEditor::class, ['school' => $school])
        ->set('key', 'fee.payment_received')
        ->set('channel', 'sms')
        ->set('body', 'Thank you {{ guardian.name }}.')
        ->call('save')
        ->assertHasNoErrors();

    expect(NotificationTemplate::where('school_id', $school->id)->where('key', 'fee.payment_received')->exists())->toBeTrue();
});

it('toasts a validation error when the template body references an undeclared variable', function (): void {
    $school = School::factory()->create();
    $admin = adminForNotificationTest($school);
    grantNotificationPermissions($admin, $school, 'core.notification.manage_templates');

    NotificationKeyRegistry::register(new NotificationKeyDefinition(
        key: 'fee.payment_received',
        variables: ['guardian.name'],
        defaultChannels: ['sms'],
        defaultAudience: 'fee_responsible',
    ));

    Livewire::actingAs($admin)
        ->test(TemplateEditor::class, ['school' => $school])
        ->set('key', 'fee.payment_received')
        ->set('channel', 'sms')
        ->set('body', 'Amount due: {{ invoice.balance }}.')
        ->call('save')
        ->assertDispatched('toast', variant: 'danger');

    expect(NotificationTemplate::where('school_id', $school->id)->exists())->toBeFalse();
});

it('sets a monthly budget for a channel', function (): void {
    $school = School::factory()->create();
    $admin = adminForNotificationTest($school);
    grantNotificationPermissions($admin, $school, 'core.notification.manage_budget');

    Livewire::actingAs($admin)
        ->test(Budget::class, ['school' => $school])
        ->call('openEditModal', 'sms')
        ->set('capMinor', '50.00')
        ->set('currency', 'USD')
        ->call('save')
        ->assertDispatched('toast');

    $budget = NotificationBudget::where('school_id', $school->id)->where('channel', 'sms')->sole();
    expect($budget->cap_minor)->toBe(5000);
});

it('lists and creates opt-outs', function (): void {
    $school = School::factory()->create();
    $admin = adminForNotificationTest($school);
    grantNotificationPermissions($admin, $school, 'core.notification.view');

    NotificationOptOut::create(['school_id' => $school->id, 'address' => '+263771111111', 'channel' => 'sms', 'reason' => 'bounce', 'opted_out_at' => now()]);

    Livewire::actingAs($admin)
        ->test(OptOuts::class, ['school' => $school])
        ->assertSee('+263771111111')
        ->call('openCreateModal')
        ->set('address', '+263772222222')
        ->set('channel', 'sms')
        ->call('create')
        ->assertDispatched('toast');

    expect(NotificationOptOut::where('school_id', $school->id)->where('address', '+263772222222')->exists())->toBeTrue();
});
