<?php

declare(strict_types=1);

namespace Modules\Core\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\EndImpersonationAction;
use Modules\Core\Domain\DataObjects\Auth\EndImpersonationData;
use Modules\Core\Models\ImpersonationSession;

/**
 * Persistent cross-page "you are impersonating someone" indicator, embedded
 * once in `layouts/app/sidebar.blade.php` (there is no other sensible place
 * for it — see `Core\Users\Impersonate`'s own docblock for the full
 * session-swap design this is the other caller of). Renders nothing unless
 * `session('impersonator_id')` is set, which `Impersonate::start()` is the
 * only place that ever sets.
 *
 * `stop()` mirrors `Impersonate::end()`'s reversal exactly: close the
 * `ImpersonationSession` audit row via `EndImpersonationAction`, then log
 * the original admin back in and forget the session keys — so an admin who
 * has navigated away from the console entirely can still always get back
 * to their own identity.
 */
final class ImpersonationBanner extends Component
{
    public function stop(): void
    {
        $sessionId = session('impersonation_session_id');
        $originalId = session('impersonator_id');

        if ($sessionId !== null) {
            $activeSession = ImpersonationSession::query()->find((int) $sessionId);

            if ($activeSession !== null && $activeSession->isActive()) {
                app(EndImpersonationAction::class)->execute(new EndImpersonationData($activeSession->id));
            }
        }

        session()->forget(['impersonator_id', 'impersonation_session_id']);

        if ($originalId !== null) {
            $original = User::find((int) $originalId);

            if ($original !== null) {
                Auth::login($original);
            }
        }

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render(): View
    {
        return view('core::livewire.impersonation-banner', [
            'impersonatedName' => session('impersonator_id') !== null ? Auth::user()?->name : null,
        ]);
    }
}
