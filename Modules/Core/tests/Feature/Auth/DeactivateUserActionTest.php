<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\DeactivateUserAction;
use Modules\Core\Domain\DataObjects\Auth\DeactivateUserData;
use Modules\Core\Domain\Support\Auth\UserStatus;

it('deactivates a user (BR-CORE-05-021)', function (): void {
    $user = User::factory()->create(['status' => UserStatus::Active]);

    $deactivated = app(DeactivateUserAction::class)->execute(new DeactivateUserData(
        userId: $user->id,
    ));

    expect($deactivated->status)->toBe(UserStatus::Inactive)
        ->and($deactivated->status->canAuthenticate())->toBeFalse();

    // Never hard-deleted — the row still exists.
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('revokes every active token on deactivation (BR-CORE-05-020)', function (): void {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $tokenA = $user->createToken('device-a');
    $tokenB = $user->createToken('device-b');

    app(DeactivateUserAction::class)->execute(new DeactivateUserData(userId: $user->id));

    expect($tokenA->accessToken->fresh()->revoked_at)->not->toBeNull()
        ->and($tokenB->accessToken->fresh()->revoked_at)->not->toBeNull();
});

it('records who deactivated the user', function (): void {
    $actor = User::factory()->create();
    $user = User::factory()->create(['status' => UserStatus::Active]);

    app(DeactivateUserAction::class)->execute(new DeactivateUserData(
        userId: $user->id,
        deactivatedByUserId: $actor->id,
    ));

    expect($user->fresh()->updated_by)->toBe($actor->id);
});
