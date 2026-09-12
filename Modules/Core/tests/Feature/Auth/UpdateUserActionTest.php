<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\UpdateUserAction;
use Modules\Core\Domain\DataObjects\Auth\UpdateUserData;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Core\Domain\Exceptions\IncompleteUserIdentityException;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\Tenant;

it('updates a user\'s identity fields and keeps name in sync', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Tendai',
        'last_name' => 'Moyo',
        'user_type' => UserType::Staff,
        'email' => 'tendai@example.com',
    ]);

    $updated = app(UpdateUserAction::class)->execute(new UpdateUserData(
        userId: $user->id,
        firstName: 'Tendai',
        lastName: 'Chidziva',
        email: 'tendai.chidziva@example.com',
        userType: UserType::Staff,
    ));

    expect($updated->last_name)->toBe('Chidziva')
        ->and($updated->name)->toBe('Tendai Chidziva')
        ->and($updated->email)->toBe('tendai.chidziva@example.com');
});

it('normalises a local Zimbabwean phone number to E.164 on update (BR-CORE-05-003)', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Staff, 'email' => 'a@example.com']);

    $updated = app(UpdateUserAction::class)->execute(new UpdateUserData(
        userId: $user->id,
        firstName: 'Rudo',
        lastName: 'Chuma',
        phone: '0771234567',
        userType: UserType::Staff,
    ));

    expect($updated->phone)->toBe('+263771234567');
});

it('rejects clearing every identifier down to none (BR-CORE-05-001)', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Staff, 'email' => 'only@example.com']);

    app(UpdateUserAction::class)->execute(new UpdateUserData(
        userId: $user->id,
        firstName: 'No',
        lastName: 'Identity',
        userType: UserType::Staff,
    ));
})->throws(IncompleteUserIdentityException::class);

it('rejects a duplicate email within the same tenant, excluding the user\'s own row', function (): void {
    $tenant = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Staff, 'email' => 'a@example.com']);
    $userB = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Staff, 'email' => 'b@example.com']);

    app(UpdateUserAction::class)->execute(new UpdateUserData(
        userId: $userB->id,
        firstName: 'B',
        lastName: 'User',
        email: 'a@example.com',
        userType: UserType::Staff,
    ));
})->throws(DuplicateRecordException::class);

it('allows saving a user with their own unchanged email (no false duplicate)', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Staff, 'email' => 'same@example.com']);

    $updated = app(UpdateUserAction::class)->execute(new UpdateUserData(
        userId: $user->id,
        firstName: 'Same',
        lastName: 'Person',
        email: 'same@example.com',
        userType: UserType::Staff,
    ));

    expect($updated->email)->toBe('same@example.com');
});
