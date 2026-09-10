<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\CreateUserAction;
use Modules\Core\Domain\DataObjects\Auth\CreateUserData;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Core\Domain\Exceptions\IncompleteUserIdentityException;
use Modules\Core\Domain\Exceptions\WeakPasswordException;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\Tenant;

it('creates a user and keeps name in sync with first/last name (BR-CORE-05-001)', function (): void {
    $tenant = Tenant::factory()->create();

    $user = app(CreateUserAction::class)->execute(new CreateUserData(
        firstName: 'Tendai',
        lastName: 'Moyo',
        email: 'tendai@example.com',
        password: 'CorrectHorse9!',
        tenantId: $tenant->id,
    ));

    expect($user->first_name)->toBe('Tendai')
        ->and($user->last_name)->toBe('Moyo')
        ->and($user->name)->toBe('Tendai Moyo')
        ->and($user->user_type)->toBe(UserType::Staff)
        ->and($user->password_changed_at)->not->toBeNull();
});

it('normalises a local Zimbabwean phone number to E.164 (BR-CORE-05-003)', function (): void {
    $tenant = Tenant::factory()->create();

    $user = app(CreateUserAction::class)->execute(new CreateUserData(
        firstName: 'Rudo',
        lastName: 'Chuma',
        phone: '0771234567',
        tenantId: $tenant->id,
    ));

    expect($user->phone)->toBe('+263771234567');
});

it('rejects a user with no email, phone, or username (BR-CORE-05-001)', function (): void {
    $tenant = Tenant::factory()->create();

    app(CreateUserAction::class)->execute(new CreateUserData(
        firstName: 'No',
        lastName: 'Identity',
        tenantId: $tenant->id,
    ));
})->throws(IncompleteUserIdentityException::class);

it('rejects a password that fails the configured policy (BR-CORE-05-004)', function (): void {
    $tenant = Tenant::factory()->create();

    app(CreateUserAction::class)->execute(new CreateUserData(
        firstName: 'Weak',
        lastName: 'Password',
        email: 'weak@example.com',
        password: 'short',
        tenantId: $tenant->id,
    ));
})->throws(WeakPasswordException::class);

it('rejects a duplicate email within the same tenant (BR-CORE-05-002)', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'dup@example.com']);

    app(CreateUserAction::class)->execute(new CreateUserData(
        firstName: 'Second',
        lastName: 'User',
        email: 'dup@example.com',
        tenantId: $tenant->id,
    ));
})->throws(DuplicateRecordException::class);

it('allows the same email to exist in two different tenants (BR-CORE-05-002)', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenantA->id, 'email' => 'shared@example.com']);

    $user = app(CreateUserAction::class)->execute(new CreateUserData(
        firstName: 'Shared',
        lastName: 'Parent',
        email: 'shared@example.com',
        tenantId: $tenantB->id,
    ));

    expect($user->tenant_id)->toBe($tenantB->id);
});
