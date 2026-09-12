<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Profile;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\RevokeTokenAction;
use Modules\Core\Domain\DataObjects\Auth\RevokeTokenData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\PersonalAccessToken;

/**
 * `Core\Profile\Devices` (Book A CORE-05 §6, screen "own"). Lists the
 * signed-in user's own Sanctum API/mobile devices (`personal_access_tokens`)
 * and lets them revoke one at a time (BR-CORE-05-011 — effective on the
 * next request, there is no cache to expire). No route parameters;
 * always acts on `Auth::user()`.
 *
 * No "current device" self-revoke guard: these tokens are issued to the
 * Next.js/Flutter clients over the API, never to this Livewire admin
 * panel's own browser session, so there is no meaningful notion of "the
 * device making this request" among the rows rendered here.
 *
 * No "revoke all" button on this screen — it lives solely on
 * `Core\Profile\Security` (see that component's docblock) so the two
 * screens don't offer the same bulk action twice; this screen links
 * there instead for that case.
 *
 * BR-CORE-05-010 (the `auth.max_devices_per_user` limit, default 5, is
 * enforced by `IssueApiTokenAction` — the oldest device is silently
 * revoked once the limit is hit) is explained in the view as context,
 * not re-enforced here.
 */
#[Title('My devices')]
#[Layout('layouts.app')]
final class Devices extends Component
{
    use Toasts;

    public function revoke(int $tokenId): void
    {
        $user = Auth::user();

        // Query the bound Core `PersonalAccessToken` model explicitly by
        // (tokenable_type, tokenable_id) rather than through
        // `$user->tokens()` — that relation is typed against Sanctum's
        // own base `PersonalAccessToken` class in `HasApiTokens`'s
        // PHPDoc, which does not carry the `isRevoked()` helper this
        // module's extended model adds, even though it resolves to the
        // same bound class at runtime.
        $token = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->find($tokenId);

        if ($token === null || $token->isRevoked()) {
            // Already gone, or never belonged to this user — a harmless
            // no-op either way rather than a confusing error for a
            // double-click or a stale row from another browser tab.
            return;
        }

        app(RevokeTokenAction::class)->execute(new RevokeTokenData(
            tokenId: $token->id,
            revokedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Device signed out.'));
    }

    public function render(): View
    {
        $user = Auth::user();

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_used_at')
            ->get();

        $maxDevices = app(SettingResolver::class)->get(
            'auth.max_devices_per_user',
            new ScopeChain(tenantId: $user->tenant_id),
        );

        return view('core::profile.devices', [
            'tokens' => $tokens,
            'maxDevices' => (int) $maxDevices,
        ]);
    }
}
