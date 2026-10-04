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

                        $schoolGroupActive = request()->routeIs('schools.*') || request()->routeIs('structure.*') || request()->routeIs('houses.*') || request()->routeIs('modules.*') || request()->routeIs('sessions.*') || request()->routeIs('settings.*') || request()->routeIs('custom-fields.*') || request()->routeIs('roles.*') || request()->routeIs('permissions.*') || request()->routeIs('numbering.*') || request()->routeIs('templates.*') || request()->routeIs('documents.*') || request()->routeIs('audit.*') || request()->routeIs('notifications.*') || request()->routeIs('files.*') || request()->routeIs('imports.*') || request()->routeIs('backups.contract-exit-export');

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
                                    <a href="{{ route('notifications.log', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-notification-3-line"></i> {{ __('Notifications') }}
                                    </a>
                                    <a href="{{ route('files.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('files.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-folder-3-line"></i> {{ __('Files') }}
                                    </a>
                                    <a href="{{ route('imports.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-upload-cloud-2-line"></i> {{ __('Import data') }}
                                    </a>
                                    <a href="{{ route('backups.contract-exit-export', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('backups.contract-exit-export') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-archive-line"></i> {{ __('Contract-exit export') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($sessionsSchool)
                        @php $studentsGroupActive = request()->routeIs('people.students.*') || request()->routeIs('people.guardians.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $studentsGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-students" aria-expanded="{{ $studentsGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-students">
                                <i class="ri ri-graduation-cap-line"></i> {{ __('Students') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $studentsGroupActive ? 'show' : '' }}" id="sidebar-group-students">
                                <div class="app-sidebar-subnav">
                                    <a href="{{ route('people.students.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.students.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-2"></i> {{ __('Directory') }}
                                    </a>
                                    <a href="{{ route('people.students.create', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.students.create') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-add-line"></i> {{ __('New student') }}
                                    </a>
                                    <a href="{{ route('people.students.duplicates', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.students.duplicates') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-fingerprint-line"></i> {{ __('Duplicate scan') }}
                                    </a>
                                    <a href="{{ route('people.guardians.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.guardians.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-parent-line"></i> {{ __('Guardians') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        @php $admissionsGroupActive = request()->routeIs('people.admissions.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $admissionsGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-admissions" aria-expanded="{{ $admissionsGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-admissions">
                                <i class="ri ri-door-open-line"></i> {{ __('Admissions') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $admissionsGroupActive ? 'show' : '' }}" id="sidebar-group-admissions">
                                <div class="app-sidebar-subnav">
                                    <a href="{{ route('people.admissions.intakes.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.admissions.intakes.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-event-line"></i> {{ __('Intakes') }}
                                    </a>
                                    <a href="{{ route('people.admissions.applications.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.admissions.applications.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-2"></i> {{ __('Applications') }}
                                    </a>
                                    <a href="{{ route('people.admissions.applications.create', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.admissions.applications.create') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-add-line"></i> {{ __('New application') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

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

                    @if ($sessionsSchool)
                        @php $financeGroupActive = request()->routeIs('finance.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $financeGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-finance" aria-expanded="{{ $financeGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-finance">
                                <i class="ri ri-scales-3-line"></i> {{ __('Finance') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $financeGroupActive ? 'show' : '' }}" id="sidebar-group-finance">
                                <div class="app-sidebar-subnav">
                                    {{--
                                        Grouped by Book B module (2026-09-13, user-reported:
                                        "the items under finance are a lot and huge we have
                                        to group them") — every link stays visible whenever
                                        the outer Finance group is open (no nested collapse),
                                        just labelled by section so a bursar can scan to the
                                        right area instead of one 28-link flat list. New
                                        Finance screens should be added under the matching
                                        section below, not appended to the bottom.
                                    --}}
                                    <div class="app-sidebar-heading">{{ __('General ledger') }}</div>
                                    <a href="{{ route('finance.accounts.tree', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.accounts.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-2"></i> {{ __('Chart of accounts') }}
                                    </a>
                                    <a href="{{ route('finance.cost-centres.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.cost-centres.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-line"></i> {{ __('Cost centres') }}
                                    </a>
                                    <a href="{{ route('finance.journals.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.journals.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-book-2-line"></i> {{ __('Journals') }}
                                    </a>
                                    <a href="{{ route('finance.posting-rules.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.posting-rules.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-route-line"></i> {{ __('Posting rules') }}
                                    </a>
                                    <a href="{{ route('finance.reports.trial-balance', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.reports.trial-balance') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-chart-line"></i> {{ __('Trial balance') }}
                                    </a>
                                    <a href="{{ route('finance.integrity.balances', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.integrity.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Balance integrity') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Currency') }}</div>
                                    <a href="{{ route('finance.currency.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.currency.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-coins-line"></i> {{ __('Currencies') }}
                                    </a>
                                    <a href="{{ route('finance.currency.rates', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.currency.rates') || request()->routeIs('finance.currency.capture-rate') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-exchange-line"></i> {{ __('Exchange rates') }}
                                    </a>
                                    <a href="{{ route('finance.currency.approve-rate', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.currency.approve-rate') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-checkbox-circle-line"></i> {{ __('Approve rates') }}
                                    </a>
                                    <a href="{{ route('finance.currency.simulate', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.currency.simulate') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-line-chart-line"></i> {{ __('Rate simulator') }}
                                    </a>
                                    <a href="{{ route('finance.currency.revaluation', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.currency.revaluation') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-refresh-line"></i> {{ __('FX revaluation') }}
                                    </a>
                                    <a href="{{ route('finance.currency.conversion-log', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.currency.conversion-log') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-history-line"></i> {{ __('Conversion log') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Fees & billing') }}</div>
                                    <a href="{{ route('finance.fees.components', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.fees.components') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-price-tag-3-line"></i> {{ __('Fee components') }}
                                    </a>
                                    <a href="{{ route('finance.fees.structures', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.fees.structures') || request()->routeIs('finance.fees.structure-builder.*') || request()->routeIs('finance.fees.structure-versions') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stack-line"></i> {{ __('Fee structures') }}
                                    </a>
                                    <a href="{{ route('finance.fees.ad-hoc-charge', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.fees.ad-hoc-charge') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-add-line"></i> {{ __('Ad hoc charge') }}
                                    </a>
                                    <a href="{{ route('finance.fees.simulator', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.fees.simulator') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calculator-line"></i> {{ __('Fee simulator') }}
                                    </a>
                                    <a href="{{ route('finance.billing.history', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.billing.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bill-line"></i> {{ __('Billing runs') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Invoicing & debtors') }}</div>
                                    <a href="{{ route('finance.invoices.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.invoices.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-3-line"></i> {{ __('Invoices') }}
                                    </a>
                                    <a href="{{ route('finance.credit-notes.create', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.credit-notes.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-reduce-line"></i> {{ __('Credit notes') }}
                                    </a>
                                    <a href="{{ route('finance.statements.generate', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.statements.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-text-line"></i> {{ __('Statements') }}
                                    </a>
                                    <a href="{{ route('finance.reports.aged-debtors', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.reports.aged-debtors') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-time-line"></i> {{ __('Aged debtors') }}
                                    </a>
                                    <a href="{{ route('finance.debtors.workbench', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.debtors.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-phone-line"></i> {{ __('Debtor workbench') }}
                                    </a>
                                    <a href="{{ route('finance.reminders.schedules', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.reminders.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alarm-warning-line"></i> {{ __('Reminder schedules') }}
                                    </a>
                                    <a href="{{ route('finance.payment-plans.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.payment-plans.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-check-line"></i> {{ __('Payment plans') }}
                                    </a>
                                    <a href="{{ route('finance.waivers.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.waivers.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hand-coin-line"></i> {{ __('Waivers & write-offs') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Till & receipting') }}</div>
                                    <a href="{{ route('finance.till.open', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.till.open') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-cash-line"></i> {{ __('Open till') }}
                                    </a>
                                    <a href="{{ route('finance.till.sessions', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.till.sessions') || request()->routeIs('finance.till.cash-up') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-history-line"></i> {{ __('Till sessions') }}
                                    </a>
                                    <a href="{{ route('finance.till.variance-approval', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.till.variance-approval') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-star-line"></i> {{ __('Variance approval') }}
                                    </a>
                                    <a href="{{ route('finance.till.banking', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.till.banking') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bank-line"></i> {{ __('Daily banking') }}
                                    </a>
                                    <a href="{{ route('finance.suspense.workbench', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.suspense.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-question-line"></i> {{ __('Suspense workbench') }}
                                    </a>
                                    <a href="{{ route('finance.reports.collections', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.reports.collections') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hand-coin-line"></i> {{ __('Collections') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Gateways & reconciliation') }}</div>
                                    <a href="{{ route('finance.gateways.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.gateways.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bank-card-line"></i> {{ __('Payment gateways') }}
                                    </a>
                                    <a href="{{ route('finance.gateways.intents', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.gateways.intents') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-exchange-dollar-line"></i> {{ __('Payment intents') }}
                                    </a>
                                    <a href="{{ route('finance.gateways.webhooks', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.gateways.webhooks') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-webhook-line"></i> {{ __('Webhook log') }}
                                    </a>
                                    <a href="{{ route('finance.bank.accounts', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.bank.accounts') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-2-line"></i> {{ __('Bank accounts') }}
                                    </a>
                                    <a href="{{ route('finance.bank.import', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.bank.import') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-upload-2-line"></i> {{ __('Import statement') }}
                                    </a>
                                    <a href="{{ route('finance.reconciliation.dashboard', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.reconciliation.dashboard') || request()->routeIs('finance.bank.matching') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-git-merge-line"></i> {{ __('Reconciliation') }}
                                    </a>
                                    <a href="{{ route('finance.reconciliation.exceptions', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.reconciliation.exceptions') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-error-warning-line"></i> {{ __('Exceptions') }}
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

                    @php $systemGroupActive = request()->routeIs('scheduling.*') || request()->routeIs('backups.index') || request()->routeIs('backups.show'); @endphp
                    <div class="app-sidebar-group">
                        <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $systemGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-system" aria-expanded="{{ $systemGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-system">
                            <i class="ri ri-pulse-line"></i> {{ __('System') }}
                            <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                        </a>
                        <div class="collapse {{ $systemGroupActive ? 'show' : '' }}" id="sidebar-group-system">
                            <div class="app-sidebar-subnav">
                                <a href="{{ route('scheduling.health') }}" class="nav-link {{ request()->routeIs('scheduling.health') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-heart-pulse-line"></i> {{ __('Health') }}
                                </a>
                                <a href="{{ route('scheduling.tasks') }}" class="nav-link {{ request()->routeIs('scheduling.tasks*') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-calendar-check-line"></i> {{ __('Scheduled tasks') }}
                                </a>
                                <a href="{{ route('scheduling.failed-jobs') }}" class="nav-link {{ request()->routeIs('scheduling.failed-jobs') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-error-warning-line"></i> {{ __('Failed jobs') }}
                                </a>
                                <a href="{{ route('scheduling.progress') }}" class="nav-link {{ request()->routeIs('scheduling.progress') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-loader-4-line"></i> {{ __('My jobs') }}
                                </a>
                                <a href="{{ route('scheduling.maintenance') }}" class="nav-link {{ request()->routeIs('scheduling.maintenance') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-tools-line"></i> {{ __('Maintenance mode') }}
                                </a>
                                <a href="{{ route('scheduling.demo-data') }}" class="nav-link {{ request()->routeIs('scheduling.demo-data') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-seedling-line"></i> {{ __('Demo data') }}
                                </a>
                                <a href="{{ route('backups.index') }}" class="nav-link {{ request()->routeIs('backups.index') || request()->routeIs('backups.show') ? 'active' : '' }}" wire:navigate>
                                    <i class="ri ri-hard-drive-2-line"></i> {{ __('Backups') }}
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
