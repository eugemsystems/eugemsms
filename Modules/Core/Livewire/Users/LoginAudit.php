<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Users;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Models\LoginAttempt;

/**
 * `Core\Users\LoginAudit` (Book A CORE-05 §6/BR-CORE-05-007). Read-only —
 * `login_attempts` is append-only and no Action ever updates/deletes a row
 * (Volume 1's append-only-at-the-grant-level doctrine).
 *
 * Gating: the spec requires `core.audit.view`, which the catalogue now
 * has (2026-09-12) — but this screen is tenant-wide with no `{school}` of
 * its own, the same "no 'any school' resolver method yet" gap documented
 * on `Users\Index`/`Show`/`Form` in `.ai/rules/auth.md`. Enforcing it here
 * correctly needs that resolver, not a single arbitrary school passed to
 * `PermissionScopeResolver`, so this screen keeps the same
 * "authenticated only, for now" precedent until that resolver exists.
 *
 * Tenant scoping: `login_attempts.user_id` is nullable — an attempt against
 * an identifier that never resolved to a user (typo'd email, brute-force
 * probe) has no `user_id` and therefore no tenant to scope it to. Those
 * rows are deliberately still shown (unscoped) rather than hidden, because
 * hiding them would hide exactly the "who is probing our login form"
 * signal this screen exists for; every row with a resolvable `user_id` is
 * scoped to the viewer's own tenant via an INNER-join-shaped `whereHas`
 * on `user_id`, `OR user_id IS NULL`.
 */
#[Title('Login audit')]
#[Layout('layouts.app')]
final class LoginAudit extends Component
{
    use InteractsWithDataTable;

    public function render(): View
    {
        $tenantId = Auth::user()?->tenant_id;

        $query = LoginAttempt::query()
            ->with('user')
            ->where(function (Builder $query) use ($tenantId): void {
                $query->whereNull('user_id')
                    ->orWhereHas('user', function (Builder $query) use ($tenantId): void {
                        $query->where('tenant_id', $tenantId);
                    });
            })
            ->orderByDesc('attempted_at');

        return view('core::users.login-audit', [
            'attempts' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'identifier' => ['label' => __('Identifier'), 'sortable' => true, 'searchable' => true],
            'user' => ['label' => __('User')],
            'guard' => [
                'label' => __('Guard'), 'sortable' => true, 'filter' => 'select',
                'options' => ['web' => __('Web'), 'sanctum' => __('Sanctum')],
            ],
            'was_successful' => [
                'label' => __('Result'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Success'), '0' => __('Failure')],
            ],
            'failure_reason' => ['label' => __('Failure reason'), 'searchable' => true],
            'ip_address' => ['label' => __('IP address'), 'searchable' => true],
            'attempted_at' => ['label' => __('Attempted'), 'sortable' => true],
        ];
    }
}
