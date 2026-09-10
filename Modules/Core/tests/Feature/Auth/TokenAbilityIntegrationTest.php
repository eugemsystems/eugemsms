<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Http\Middleware\EnforceTokenAbility;

/**
 * AC-CORE-05-004: a role granting fee viewing is not enough — the
 * token itself must also carry the `fees:read` ability. Ability is a
 * ceiling on role (BR-CORE-05-008), independent of anything role-based.
 */
it('returns 403 INSUFFICIENT_SCOPE when the token lacks the required ability, even for an otherwise-permitted user', function (): void {
    Route::middleware(['api', EnforceTokenAbility::class.':fees.read'])
        ->get('/__test/fees-balance', fn () => response()->json(['ok' => true]));

    $user = User::factory()->create();
    Sanctum::actingAs($user, ['profile:read']);

    $response = $this->getJson('/__test/fees-balance');

    $response->assertStatus(403);
    expect($response->json('error.code'))->toBe('INSUFFICIENT_SCOPE');
});

it('allows the request when the token carries the required ability', function (): void {
    Route::middleware(['api', EnforceTokenAbility::class.':fees.read'])
        ->get('/__test/fees-balance-ok', fn () => response()->json(['ok' => true]));

    $user = User::factory()->create();
    Sanctum::actingAs($user, ['fees.read']);

    $this->getJson('/__test/fees-balance-ok')->assertOk()->assertJson(['ok' => true]);
});
