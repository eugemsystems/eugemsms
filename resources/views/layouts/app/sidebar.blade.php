<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body>
        <div class="app-shell">
            <aside class="app-sidebar">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />

                <div class="px-3 mb-2 d-flex flex-column gap-2">
                    @livewire(\Modules\Core\Livewire\SchoolSwitcher::class)
                    @livewire(\Modules\Core\Livewire\SessionSwitcher::class)
                </div>

                <nav class="app-sidebar-nav">
                    @php
                        // A model instance, not a bare id (2026-09-12 bugfix,
                        // user-reported: "settings, roles and permissions urls
                        // are still showing numbers"): route()/redirect()
                        // only resolve by HasUlid::getRouteKeyName() when
                        // given the MODEL — a raw int is plugged into the URL
                        // literally, which 404s now that binding requires the
                        // ulid. This was also the likely cause of the school
                        // switcher looking like it did nothing: reloading
                        // whatever page was open landed back on one of these
                        // broken numeric-id links.
                        $sessionsSchoolId = \Modules\Core\Domain\Support\SchoolContext::currentId() ?? \Modules\Core\Domain\Support\ActiveSchoolResolver::resolveId(auth()->user());
                        $sessionsSchool = $sessionsSchoolId !== null ? \Modules\Core\Models\School::find($sessionsSchoolId) : null;

                        $schoolGroupActive = request()->routeIs('schools.*') || request()->routeIs('structure.*') || request()->routeIs('houses.*') || request()->routeIs('modules.*') || request()->routeIs('sessions.*') || request()->routeIs('settings.*') || request()->routeIs('custom-fields.*') || request()->routeIs('roles.*') || request()->routeIs('permissions.*') || request()->routeIs('numbering.*') || request()->routeIs('templates.*') || request()->routeIs('documents.*') || request()->routeIs('audit.*');

                        $identityGroupActive = request()->routeIs('users.*') || request()->routeIs('impersonate.*') || request()->routeIs('login-audit.*');
                    @endphp

                    <div class="app-sidebar-heading">{{ __('Platform') }}</div>
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" wire:navigate>
                        <i class="ri ri-home-5-line"></i> {{ __('Dashboard') }}
                    </a>

                    {{-- Collapsible nav groups: plain Bootstrap 5 `collapse` (already
                    loaded app-wide, see resources/js/app.js) rather than TEMPLATE's
                    own custom menu.js widget — that widget is entangled with
                    horizontal-menu-only logic (PerfectScrollbar, slide arrows) that
                    doesn't cleanly separate from the vertical accordion behaviour
                    actually needed here (2026-09-12). Initial expand/collapse state
                    is rendered server-side from $xGroupActive so the correct state
                    shows on first paint with no JS required; Bootstrap's own
                    data-bs-toggle="collapse" click handling takes over from there. --}}
                    <div class="app-sidebar-group">
                        <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $schoolGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-school" aria-expanded="{{ $schoolGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-school">
                            <i class="ri ri-school-line"></i> {{ __('School setup') }}
                            <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                        </a>
                        <div class="collapse {{ $schoolGroupActive ? 'show' : '' }}" id="sidebar-group-school">
                            <div class="app-sidebar-subnav">
                                <a href="{{ route('schools.index') }}" class="nav-link {{ request()->routeIs('schools.*') || request()->routeIs('structure.*') || request()->routeIs('houses.*') || request()->routeIs('modules.*') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-school-line"></i> {{ __('Schools') }}
                                </a>
                                @if ($sessionsSchool)
                                    <a href="{{ route('sessions.years', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('sessions.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-event-line"></i> {{ __('Academic sessions') }}
                                    </a>
                                    <a href="{{ route('settings.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('settings.*') || request()->routeIs('custom-fields.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-settings-3-line"></i> {{ __('Settings') }}
                                    </a>
                                    <a href="{{ route('roles.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-user-line"></i> {{ __('Roles') }}
                                    </a>
                                    <a href="{{ route('permissions.explorer', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-key-2-line"></i> {{ __('Permissions') }}
                                    </a>
                                    <a href="{{ route('numbering.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('numbering.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hashtag"></i> {{ __('Numbering') }}
                                    </a>
                                    <a href="{{ route('templates.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('templates.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-text-line"></i> {{ __('Templates') }}
                                    </a>
                                    <a href="{{ route('documents.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('documents.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-3-line"></i> {{ __('Documents') }}
                                    </a>
                                    <a href="{{ route('audit.explorer', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-shield-2-line"></i> {{ __('Audit') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($sessionsSchool)
                        @php $approvalsGroupActive = request()->routeIs('approvals.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $approvalsGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-approvals" aria-expanded="{{ $approvalsGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-approvals">
                                <i class="ri ri-checkbox-multiple-line"></i> {{ __('Approvals') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $approvalsGroupActive ? 'show' : '' }}" id="sidebar-group-approvals">
                                <div class="app-sidebar-subnav">
                                    <a href="{{ route('approvals.queue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('approvals.queue') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-inbox-line"></i> {{ __('My approvals') }}
                                    </a>
                                    <a href="{{ route('approvals.mine', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('approvals.mine') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-send-plane-line"></i> {{ __('My requests') }}
                                    </a>
                                    <a href="{{ route('approvals.delegations', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('approvals.delegations') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-shared-line"></i> {{ __('Delegations') }}
                                    </a>
                                    <a href="{{ route('approvals.chains', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('approvals.chains*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-git-branch-line"></i> {{ __('Chains') }}
                                    </a>
                                    <a href="{{ route('approvals.sla-report', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('approvals.sla-report') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-time-line"></i> {{ __('SLA report') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <a href="{{ route('feature-flags.index') }}" class="nav-link {{ request()->routeIs('feature-flags.*') ? 'active' : '' }}" wire:navigate>
                        <i class="ri ri-flag-line"></i> {{ __('Feature flags') }}
                    </a>

                    <div class="app-sidebar-group">
                        <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $identityGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-identity" aria-expanded="{{ $identityGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-identity">
                            <i class="ri ri-team-line"></i> {{ __('Users & security') }}
                            <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                        </a>
                        <div class="collapse {{ $identityGroupActive ? 'show' : '' }}" id="sidebar-group-identity">
                            <div class="app-sidebar-subnav">
                                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-team-line"></i> {{ __('Users') }}
                                </a>
                                <a href="{{ route('impersonate.index') }}" class="nav-link {{ request()->routeIs('impersonate.*') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-spy-line"></i> {{ __('Impersonation') }}
                                </a>
                                <a href="{{ route('login-audit.index') }}" class="nav-link {{ request()->routeIs('login-audit.*') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-history-line"></i> {{ __('Login audit') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="mt-auto">
                    <x-desktop-user-menu :name="auth()->user()->name" />
                </div>
            </aside>

            <div class="app-main">
                <header class="app-topbar d-lg-none">
                    <button type="button" class="btn btn-icon app-sidebar-toggle" data-sidebar-toggle aria-label="{{ __('Toggle menu') }}">
                        <i class="ri ri-menu-line fs-4"></i>
                    </button>

                    <x-app-logo href="{{ route('dashboard') }}" wire:navigate />
                </header>

                {{--
                    BR-CORE-03-007: every admin page must show this banner
                    while viewing a non-live (historical) term. It only
                    fires on pages that have actually resolved
                    `SessionContext` for the request — currently the
                    `Core\Sessions\*` screens via `InteractsWithSession`.
                    Full "every admin page, unconditionally" coverage needs
                    `SetSchoolContext`/`SetSessionContext` wired globally,
                    which no route group in this app does yet (see
                    `InteractsWithSchool`'s own docblock) — a separate,
                    larger change, not done as a side effect of CORE-03.
                --}}
                @if (\Modules\Core\Domain\Support\SessionContext::isSet() && ! \Modules\Core\Domain\Support\SessionContext::isCurrentLiveTerm())
                    <div class="serp-historical-banner">
                        <i class="ri ri-history-line me-1"></i>
                        {{ __('You are viewing a historical or non-current academic period — changes here will not affect live records.') }}
                    </div>
                @endif

                {{--
                    Book A CORE-05 BR-CORE-05-017: an admin impersonating
                    another user must always be able to get back to their
                    own identity, from any page — see
                    `Modules\Core\Livewire\ImpersonationBanner`'s own
                    docblock. Same "no global context-resolution middleware
                    yet" caveat as the historical-period banner above:
                    this only renders once `session('impersonator_id')` is
                    set, which only `Core\Users\Impersonate` ever sets.
                --}}
                @livewire(\Modules\Core\Livewire\ImpersonationBanner::class)

                <main class="app-content">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <div id="serp-toast-region" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;"></div>

        {{--
            SchoolSwitcher/SessionSwitcher (2026-09-12, user-reported:
            "its just blinking but nothing changes when i switch the
            school") force a hard `window.location.reload()` instead of a
            Livewire-response redirect, so their own `toast()` dispatch
            never survives to be caught client-side — this reads back a
            session-flashed one instead (see resources/js/app.js's
            `livewire:init` listener).
        --}}
        @if (session('serp_toast'))
            <script>window.__serpFlashedToast = @json(session('serp_toast'));</script>
        @endif
    </body>
</html>
