<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Users;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\EndImpersonationAction;
use Modules\Core\Domain\Actions\Auth\StartImpersonationAction;
use Modules\Core\Domain\DataObjects\Auth\EndImpersonationData;
use Modules\Core\Domain\DataObjects\Auth\StartImpersonationData;
use Modules\Core\Domain\Exceptions\ReasonRequiredException;
use Modules\Core\Models\ImpersonationSession;

/**
 * `Core\Users\Impersonate` (Book A CORE-05 §6/BR-CORE-05-017..019). Marked
 * "(vendor)" in the spec — meant for platform-vendor support staff, not a
 * school's own admins — and gated on `core.user.impersonate`. That
 * permission is not yet seeded in this database (zero rows, confirmed via
 * `Permission::where('name', 'core.user.impersonate')->count()`), so
 * `->can()` would make this screen permanently unreachable for everyone.
 * Mirroring the precedent already established by `Core\Users\Index` (and,
 * before it, `InteractsWithSchool`) this screen gates on authentication
 * only for now and will start checking `core.user.impersonate` the moment
 * CORE-05's permission catalogue is seeded.
 *
 * **The actual "act as this user" mechanism (session-swap).** `StartImpersonationAction`
 * only writes the `ImpersonationSession` audit row — it never touches the
 * browser session or the authenticated guard, and the spec does not
 * describe that wiring anywhere else in this codebase. This component
 * implements it directly (a framework-level auth operation, not a
 * database write, so it stays outside the Action pattern's "no DB writes
 * outside an Action" rule):
 *
 *   - `start()`: after `StartImpersonationAction` succeeds, this component
 *     stashes the REAL admin's id in `session('impersonator_id')` (plus
 *     the session row's id in `session('impersonation_session_id')` so
 *     `end()` can tell which browser session it is ending), then calls
 *     `Auth::login($target)`. From that request on, `Auth::id()` reports
 *     the impersonated user — which is why every helper below reads the
 *     true operator from `session('impersonator_id') ?? Auth::id()`
 *     rather than `Auth::id()` directly.
 *   - `end()`: calls `EndImpersonationAction` to close the audit row, and
 *     — only if the row being ended is the one THIS browser session is
 *     currently riding on — forgets both session keys and calls
 *     `Auth::login($originalAdmin)` to swap back. Ending a different (e.g.
 *     stale, another-device) session from the list below closes its audit
 *     row without touching the current browser's identity.
 *   - The persistent "Stop impersonating" banner (`ImpersonationBanner`,
 *     embedded in `layouts/app/sidebar.blade.php`) is the other caller of
 *     this same reversal, for when the admin is impersonating and has
 *     navigated away from this screen entirely — a real admin must always
 *     be able to get back without returning here first.
 *
 * Nesting is refused: an admin already impersonating someone (i.e.
 * `session('impersonator_id')` is already set) cannot start a second
 * impersonation from this screen without ending the first.
 *
 * BR-CORE-05-018 (financial mutation / permission change / bulk export
 * blocked while impersonating) is enforced by `ImpersonationGuard`, called
 * from within each blocked operation's own Action once those Actions
 * exist — there is no request-lifecycle wiring yet that resolves "the
 * currently active `ImpersonationSession` for this request" automatically
 * (no middleware sets it, no context singleton holds it). Building that
 * is out of scope here; this screen only starts/ends the audit row and
 * the session-swap.
 */
#[Title('Impersonation console')]
#[Layout('layouts.app')]
final class Impersonate extends Component
{
    use Toasts;

    #[Url(as: 'user')]
    public ?int $preselectedUserId = null;

    public string $userSearch = '';

    public ?int $targetUserId = null;

    public string $reason = '';

    public string $ticketReference = '';

    public string $consentReference = '';

    public function mount(): void
    {
        if ($this->preselectedUserId !== null && $this->findCandidateUser($this->preselectedUserId) !== null) {
            $this->targetUserId = $this->preselectedUserId;
        }
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function candidateUsers(): Collection
    {
        $term = trim($this->userSearch);

        return User::query()
            ->where('tenant_id', Auth::user()?->tenant_id)
            ->where('id', '!=', Auth::id())
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('username', 'like', "%{$term}%");
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get();
    }

    /**
     * @return Collection<int, ImpersonationSession>
     */
    #[Computed]
    public function pastSessions(): Collection
    {
        return ImpersonationSession::query()
            ->where('impersonator_id', $this->currentOperatorId())
            ->with('impersonated')
            ->latest('started_at')
            ->limit(25)
            ->get();
    }

    public function isCurrentlyImpersonating(): bool
    {
        return session('impersonator_id') !== null;
    }

    public function start(): void
    {
        if ($this->isCurrentlyImpersonating()) {
            $this->toast(__('You are already impersonating someone — stop that session first.'), 'danger');

            return;
        }

        $this->validate([
            'targetUserId' => ['required', 'integer'],
            'reason' => ['required', 'string'],
            'ticketReference' => ['required', 'string'],
            'consentReference' => [app()->environment('production') ? 'required' : 'nullable', 'string'],
        ]);

        $target = $this->findCandidateUser($this->targetUserId);

        if ($target === null) {
            $this->addError('targetUserId', __('Select a valid user to impersonate.'));

            return;
        }

        $operatorId = (int) Auth::id();

        try {
            $session = app(StartImpersonationAction::class)->execute(new StartImpersonationData(
                impersonatorId: $operatorId,
                impersonatedId: $target->id,
                reason: $this->reason,
                ticketReference: $this->ticketReference,
                consentReference: $this->consentReference !== '' ? $this->consentReference : null,
            ));
        } catch (ReasonRequiredException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        session([
            'impersonator_id' => $operatorId,
            'impersonation_session_id' => $session->id,
        ]);

        Auth::login($target);

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function end(int $sessionId): void
    {
        $session = ImpersonationSession::query()
            ->where('impersonator_id', $this->currentOperatorId())
            ->find($sessionId);

        if ($session === null) {
            return;
        }

        app(EndImpersonationAction::class)->execute(new EndImpersonationData($session->id));

        if (session('impersonation_session_id') === $sessionId) {
            $this->reverseSessionSwap();

            $this->redirect(route('dashboard'), navigate: false);

            return;
        }

        unset($this->pastSessions);

        $this->toast(__('Impersonation session ended.'));
    }

    /**
     * The real admin's id, whether or not they are currently impersonating
     * someone (in which case `Auth::id()` reports the impersonated user).
     */
    private function currentOperatorId(): int
    {
        return (int) (session('impersonator_id') ?? Auth::id());
    }

    private function reverseSessionSwap(): void
    {
        $originalId = session('impersonator_id');

        session()->forget(['impersonator_id', 'impersonation_session_id']);

        if ($originalId === null) {
            return;
        }

        $original = User::find((int) $originalId);

        if ($original !== null) {
            Auth::login($original);
        }
    }

    private function findCandidateUser(int $userId): ?User
    {
        return User::query()
            ->where('tenant_id', Auth::user()?->tenant_id)
            ->where('id', '!=', Auth::id())
            ->find($userId);
    }

    public function render(): View
    {
        return view('core::users.impersonate');
    }
}
