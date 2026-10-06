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

                        @php $staffGroupActive = request()->routeIs('people.staff.*') || request()->routeIs('people.establishment.*') || request()->routeIs('people.allocation.*') || request()->routeIs('people.leave.*') || request()->routeIs('people.duty.*') || request()->routeIs('people.appraisal.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $staffGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-staff" aria-expanded="{{ $staffGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-staff">
                                <i class="ri ri-team-line"></i> {{ __('Staff') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $staffGroupActive ? 'show' : '' }}" id="sidebar-group-staff">
                                <div class="app-sidebar-subnav">
                                    <a href="{{ route('people.staff.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.staff.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-2"></i> {{ __('Directory') }}
                                    </a>
                                    <a href="{{ route('people.staff.create', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.staff.create') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-add-line"></i> {{ __('New staff') }}
                                    </a>
                                    <a href="{{ route('people.establishment.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.establishment.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-line"></i> {{ __('Establishment') }}
                                    </a>
                                    <a href="{{ route('people.allocation.matrix', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.allocation.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-grid-line"></i> {{ __('Allocation matrix') }}
                                    </a>
                                    <a href="{{ route('people.leave.balances', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.leave.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-close-line"></i> {{ __('Leave') }}
                                    </a>
                                    <a href="{{ route('people.duty.rosters', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.duty.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-user-line"></i> {{ __('Duty rosters') }}
                                    </a>
                                    <a href="{{ route('people.appraisal.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.appraisal.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-star-line"></i> {{ __('Appraisals') }}
                                    </a>
                                    <a href="{{ route('people.staff.compliance', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('people.staff.compliance') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Compliance') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($sessionsSchool)
                        @php $alumniGroupActive = request()->routeIs('alumni.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $alumniGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-alumni" aria-expanded="{{ $alumniGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-alumni">
                                <i class="ri ri-graduation-cap-line"></i> {{ __('Alumni') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $alumniGroupActive ? 'show' : '' }}" id="sidebar-group-alumni">
                                <div class="app-sidebar-subnav">
                                    <a href="{{ route('alumni.directory.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('alumni.directory.index', 'alumni.directory.show') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-contacts-book-line"></i> {{ __('Directory') }}
                                    </a>
                                    <a href="{{ route('alumni.events.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('alumni.events.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-event-line"></i> {{ __('Events') }}
                                    </a>
                                    <a href="{{ route('alumni.campaigns.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('alumni.campaigns.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-funds-line"></i> {{ __('Campaigns') }}
                                    </a>
                                    <a href="{{ route('alumni.pledges.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('alumni.pledges.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hand-heart-line"></i> {{ __('Pledges') }}
                                    </a>
                                    <a href="{{ route('alumni.donations.record', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('alumni.donations.record') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-gift-line"></i> {{ __('Record a donation') }}
                                    </a>
                                    <a href="{{ route('alumni.endowments.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('alumni.endowments.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bank-line"></i> {{ __('Endowments') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($sessionsSchool)
                        @php $academicGroupActive = request()->routeIs('academic.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $academicGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-academic" aria-expanded="{{ $academicGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-academic">
                                <i class="ri ri-book-open-line"></i> {{ __('Academic') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $academicGroupActive ? 'show' : '' }}" id="sidebar-group-academic">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('Curriculum') }}</div>
                                    <a href="{{ route('academic.curriculum.frameworks', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.curriculum.frameworks') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-flag-line"></i> {{ __('Frameworks') }}
                                    </a>
                                    <a href="{{ route('academic.curriculum.subjects', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.curriculum.subjects') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-book-2-line"></i> {{ __('Subjects') }}
                                    </a>
                                    <a href="{{ route('academic.curriculum.groups', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.curriculum.groups') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stack-line"></i> {{ __('Subject groups') }}
                                    </a>
                                    <a href="{{ route('academic.curriculum.offerings', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.curriculum.offerings') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-grid-line"></i> {{ __('Level offerings') }}
                                    </a>
                                    <a href="{{ route('academic.curriculum.pathways', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.curriculum.pathways') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-signpost-line"></i> {{ __('Pathways') }}
                                    </a>
                                    <a href="{{ route('academic.curriculum.selection-rules', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.curriculum.selection-rules') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-ruler-line"></i> {{ __('Selection rules') }}
                                    </a>
                                    <a href="{{ route('academic.curriculum.prerequisites', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.curriculum.prerequisites') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-git-branch-line"></i> {{ __('Prerequisites') }}
                                    </a>
                                    <a href="{{ route('academic.curriculum.syllabi', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.curriculum.syllabi') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-text-line"></i> {{ __('Syllabi') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Enrolment') }}</div>
                                    <a href="{{ route('academic.allocation.classes', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.allocation.classes') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-door-open-line"></i> {{ __('Class allocation') }}
                                    </a>
                                    <a href="{{ route('academic.groups.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.groups.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-group-line"></i> {{ __('Teaching groups') }}
                                    </a>
                                    <a href="{{ route('academic.selection.approvals', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.selection.approvals') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-checkbox-circle-line"></i> {{ __('Selection approvals') }}
                                    </a>
                                    <a href="{{ route('academic.enrolment.billing-check', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.enrolment.billing-check') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Billing reconciliation') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Attendance') }}</div>
                                    <a href="{{ route('academic.attendance.mark', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.attendance.mark') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-checkbox-multiple-line"></i> {{ __('Mark register') }}
                                    </a>
                                    <a href="{{ route('academic.attendance.daily', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.attendance.daily') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-check-line"></i> {{ __('Daily overview') }}
                                    </a>
                                    <a href="{{ route('academic.attendance.compliance', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.attendance.compliance') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-follow-line"></i> {{ __('Marking compliance') }}
                                    </a>
                                    <a href="{{ route('academic.attendance.chronic', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.attendance.chronic') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alert-line"></i> {{ __('Chronic absentees') }}
                                    </a>
                                    <a href="{{ route('academic.attendance.reason-codes', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.attendance.reason-codes') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-settings-3-line"></i> {{ __('Reason codes') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Assessment') }}</div>
                                    <a href="{{ route('academic.grading.scales', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.grading.scales') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-medal-line"></i> {{ __('Grading scales') }}
                                    </a>
                                    <a href="{{ route('academic.assessment.types', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.assessment.types') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-3"></i> {{ __('Assessment types') }}
                                    </a>
                                    <a href="{{ route('academic.assessment.planner', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.assessment.planner') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-2-line"></i> {{ __('Assessment planner') }}
                                    </a>
                                    <a href="{{ route('academic.results.compute', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.results.compute') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-funds-line"></i> {{ __('Compute results') }}
                                    </a>
                                    <a href="{{ route('academic.results.comments', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.results.comments') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-chat-3-line"></i> {{ __('Comment bank') }}
                                    </a>
                                    <a href="{{ route('academic.results.review', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.results.review') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-check-double-line"></i> {{ __('Review results') }}
                                    </a>
                                    <a href="{{ route('academic.report-cards.run', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.report-cards.run') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-text-line"></i> {{ __('Generate report cards') }}
                                    </a>
                                    <a href="{{ route('academic.report-cards.withheld', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.report-cards.withheld') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-lock-line"></i> {{ __('Withheld reports') }}
                                    </a>
                                    <a href="{{ route('academic.report-cards.publish', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.report-cards.publish') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-send-plane-line"></i> {{ __('Publish report cards') }}
                                    </a>
                                    <a href="{{ route('academic.results.transcripts', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.results.transcripts') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-graduation-cap-line"></i> {{ __('Transcripts') }}
                                    </a>
                                    <a href="{{ route('academic.results.analytics', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.results.analytics') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-box-line"></i> {{ __('Performance analytics') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($sessionsSchool)
                        @php $academicDepthGroupActive = request()->routeIs('academic.timetable.*') || request()->routeIs('academic.projects.*') || request()->routeIs('academic.exams.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $academicDepthGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-academic-depth" aria-expanded="{{ $academicDepthGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-academic-depth">
                                <i class="ri ri-calendar-todo-line"></i> {{ __('Academic Depth') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $academicDepthGroupActive ? 'show' : '' }}" id="sidebar-group-academic-depth">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('Timetable') }}</div>
                                    <a href="{{ route('academic.timetable.structures', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.structures') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-line"></i> {{ __('Period structures') }}
                                    </a>
                                    <a href="{{ route('academic.timetable.venues', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.venues') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-line"></i> {{ __('Venues') }}
                                    </a>
                                    <a href="{{ route('academic.timetable.constraints', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.constraints') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-line"></i> {{ __('Constraints') }}
                                    </a>
                                    <a href="{{ route('academic.timetable.requirements', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.requirements') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check"></i> {{ __('Requirements') }}
                                    </a>
                                    <a href="{{ route('academic.timetable.generate', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.generate') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-magic-line"></i> {{ __('Generate') }}
                                    </a>
                                    <a href="{{ route('academic.timetable.clashes', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.clashes') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-error-warning-line"></i> {{ __('Clash inspector') }}
                                    </a>
                                    <a href="{{ route('academic.timetable.views', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.views') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-table-line"></i> {{ __('Views') }}
                                    </a>
                                    <a href="{{ route('academic.timetable.cover', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.cover') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-follow-line"></i> {{ __('Daily cover') }}
                                    </a>
                                    <a href="{{ route('academic.timetable.exceptions', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.exceptions') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-close-circle-line"></i> {{ __('Exceptions') }}
                                    </a>
                                    <a href="{{ route('academic.timetable.exam-planner', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.timetable.exam-planner') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-flag-2-line"></i> {{ __('Exam slot planner') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Projects (SBP/CALA)') }}</div>
                                    <a href="{{ route('academic.projects.instruments', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.projects.instruments') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-settings-4-line"></i> {{ __('Instruments') }}
                                    </a>
                                    <a href="{{ route('academic.projects.briefs', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.projects.briefs') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-3-line"></i> {{ __('Briefs') }}
                                    </a>
                                    <a href="{{ route('academic.projects.rubrics', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.projects.rubrics') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-ruler-2-line"></i> {{ __('Rubrics') }}
                                    </a>
                                    <a href="{{ route('academic.projects.approve', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.projects.approve') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stamp-line"></i> {{ __('Brief approval') }}
                                    </a>
                                    <a href="{{ route('academic.projects.tracker', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.projects.tracker') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-progress-4-line"></i> {{ __('Progress tracker') }}
                                    </a>
                                    <a href="{{ route('academic.projects.mark', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.projects.mark') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-edit-2-line"></i> {{ __('Mark') }}
                                    </a>
                                    <a href="{{ route('academic.projects.moderate', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.projects.moderate') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-scales-3-line"></i> {{ __('Moderate') }}
                                    </a>
                                    <a href="{{ route('academic.projects.verify', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.projects.verify') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Verify') }}
                                    </a>
                                    <a href="{{ route('academic.projects.cala-archive', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.projects.cala-archive') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-archive-line"></i> {{ __('CALA archive') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Examinations') }}</div>
                                    <a href="{{ route('academic.exams.sessions', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.sessions') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-event-line"></i> {{ __('Sessions') }}
                                    </a>
                                    <a href="{{ route('academic.exams.papers', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.papers') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-text-line"></i> {{ __('Papers') }}
                                    </a>
                                    <a href="{{ route('academic.exams.paper-vault', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.paper-vault') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-lock-2-line"></i> {{ __('Secure paper vault') }}
                                    </a>
                                    <a href="{{ route('academic.exams.candidates', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.candidates') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-line"></i> {{ __('Candidates') }}
                                    </a>
                                    <a href="{{ route('academic.exams.seating', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.seating') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-layout-grid-line"></i> {{ __('Seating plan') }}
                                    </a>
                                    <a href="{{ route('academic.exams.invigilation', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.invigilation') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-eye-line"></i> {{ __('Invigilation') }}
                                    </a>
                                    <a href="{{ route('academic.exams.scripts', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.scripts') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-shield-2-line"></i> {{ __('Script tracking') }}
                                    </a>
                                    <a href="{{ route('academic.exams.mark-entry', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.mark-entry') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-edit-line"></i> {{ __('Mark entry') }}
                                    </a>
                                    <a href="{{ route('academic.exams.variance', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.variance') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-git-compare-line"></i> {{ __('Variance review') }}
                                    </a>
                                    <a href="{{ route('academic.exams.moderate', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.moderate') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-scales-line"></i> {{ __('Moderate') }}
                                    </a>
                                    <a href="{{ route('academic.exams.arrangements', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.arrangements') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-wheelchair-line"></i> {{ __('Special arrangements') }}
                                    </a>
                                    <a href="{{ route('academic.exams.malpractice', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.malpractice') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alarm-warning-line"></i> {{ __('Malpractice') }}
                                    </a>
                                    <a href="{{ route('academic.exams.results', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.exams.results') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-trophy-line"></i> {{ __('Results') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($sessionsSchool)
                        @php $learningGroupActive = request()->routeIs('academic.lms.*') || request()->routeIs('academic.cbt.*') || request()->routeIs('academic.library.*') || request()->routeIs('academic.quality.*') || request()->routeIs('academic.supervision.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $learningGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-learning" aria-expanded="{{ $learningGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-learning">
                                <i class="ri ri-computer-line"></i> {{ __('Learning & Teaching') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $learningGroupActive ? 'show' : '' }}" id="sidebar-group-learning">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('E-learning') }}</div>
                                    <a href="{{ route('academic.lms.spaces', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.lms.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-book-read-line"></i> {{ __('Course spaces') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Computer-based testing') }}</div>
                                    <a href="{{ route('academic.cbt.bank', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.cbt.bank') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-database-2-line"></i> {{ __('Question bank') }}
                                    </a>
                                    <a href="{{ route('academic.cbt.builder', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.cbt.builder') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-edit-line"></i> {{ __('Tests') }}
                                    </a>
                                    <a href="{{ route('academic.cbt.monitor', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.cbt.monitor') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-radar-line"></i> {{ __('Monitor') }}
                                    </a>
                                    <a href="{{ route('academic.cbt.marking', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.cbt.marking') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-edit-box-line"></i> {{ __('Manual marking') }}
                                    </a>
                                    <a href="{{ route('academic.cbt.item-analysis', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.cbt.item-analysis') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-2-line"></i> {{ __('Item analysis') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Library') }}</div>
                                    <a href="{{ route('academic.library.catalogue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.library.catalogue') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-book-2-line"></i> {{ __('Catalogue') }}
                                    </a>
                                    <a href="{{ route('academic.library.circulation', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.library.circulation') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-arrow-left-right-line"></i> {{ __('Circulation desk') }}
                                    </a>
                                    <a href="{{ route('academic.library.bulk-issue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.library.bulk-issue') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stack-line"></i> {{ __('Bulk textbook issue') }}
                                    </a>
                                    <a href="{{ route('academic.library.overdue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.library.overdue') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alarm-warning-line"></i> {{ __('Overdue') }}
                                    </a>
                                    <a href="{{ route('academic.library.stock-take', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.library.stock-take') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-barcode-box-line"></i> {{ __('Stock-take') }}
                                    </a>
                                    <a href="{{ route('academic.library.acquisitions', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.library.acquisitions') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shopping-cart-line"></i> {{ __('Acquisitions') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Teaching quality') }}</div>
                                    <a href="{{ route('academic.supervision.schemes', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.supervision.schemes') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-3-line"></i> {{ __('Schemes of work') }}
                                    </a>
                                    <a href="{{ route('academic.supervision.lesson-plans', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.supervision.lesson-plans') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-draft-line"></i> {{ __('Lesson plans') }}
                                    </a>
                                    <a href="{{ route('academic.supervision.coverage', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.supervision.coverage') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-traffic-light-line"></i> {{ __('Coverage tracker') }}
                                    </a>
                                    <a href="{{ route('academic.supervision.observe', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.supervision.observe') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-eye-line"></i> {{ __('Observe a lesson') }}
                                    </a>
                                    <a href="{{ route('academic.supervision.observations', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.supervision.observations') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-history-line"></i> {{ __('Observation history') }}
                                    </a>
                                    <a href="{{ route('academic.supervision.meetings', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.supervision.meetings') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-team-line"></i> {{ __('Department meetings') }}
                                    </a>
                                    <a href="{{ route('academic.supervision.dashboard', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('academic.supervision.dashboard') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-dashboard-line"></i> {{ __('Teacher dashboard') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($sessionsSchool)
                        @php $boardingGroupActive = request()->routeIs('boarding.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $boardingGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-boarding" aria-expanded="{{ $boardingGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-boarding">
                                <i class="ri ri-hotel-line"></i> {{ __('Boarding') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $boardingGroupActive ? 'show' : '' }}" id="sidebar-group-boarding">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('Hostels & allocation') }}</div>
                                    <a href="{{ route('boarding.hostels.structure', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.hostels.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-2-line"></i> {{ __('Hostel structure') }}
                                    </a>
                                    <a href="{{ route('boarding.allocation.board', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.allocation.board') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-layout-grid-line"></i> {{ __('Occupancy board') }}
                                    </a>
                                    <a href="{{ route('boarding.allocation.run', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.allocation.run') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-play-circle-line"></i> {{ __('Bulk allocation run') }}
                                    </a>
                                    <a href="{{ route('boarding.allocation.waitlist', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.allocation.waitlist') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-ordered"></i> {{ __('Waiting list') }}
                                    </a>
                                    <a href="{{ route('boarding.allocation.constraints', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.allocation.constraints') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-ruler-line"></i> {{ __('Allocation constraints') }}
                                    </a>
                                    <a href="{{ route('boarding.allocation.incompatibilities', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.allocation.incompatibilities') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-unfollow-line"></i> {{ __('Incompatibilities') }}
                                    </a>
                                    <a href="{{ route('boarding.inspections.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.inspections.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-clipboard-line"></i> {{ __('Inspections') }}
                                    </a>
                                    <a href="{{ route('boarding.damages.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.damages.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hammer-line"></i> {{ __('Damages') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Roll call & movement') }}</div>
                                    <a href="{{ route('boarding.rollcall.take', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.rollcall.take') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-checkbox-multiple-line"></i> {{ __('Take roll call') }}
                                    </a>
                                    <a href="{{ route('boarding.rollcall.board', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.rollcall.board') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-dashboard-line"></i> {{ __('Roll call board') }}
                                    </a>
                                    <a href="{{ route('boarding.rollcall.incidents', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.rollcall.incidents') || request()->routeIs('boarding.rollcall.incident') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alarm-warning-line"></i> {{ __('Incident console') }}
                                    </a>
                                    <a href="{{ route('boarding.rollcall.escalation', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.rollcall.escalation') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-git-branch-line"></i> {{ __('Escalation & points') }}
                                    </a>
                                    <a href="{{ route('boarding.movement.log', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.movement.log') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-route-line"></i> {{ __('Movement log') }}
                                    </a>
                                    <a href="{{ route('boarding.movement.checkpoints', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.movement.checkpoints') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-map-pin-line"></i> {{ __('Checkpoints') }}
                                    </a>
                                    <a href="{{ route('boarding.occupancy.live', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.occupancy.live') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-pulse-line"></i> {{ __('Live occupancy') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Exeats, gate & visitors') }}</div>
                                    <a href="{{ route('boarding.exeats.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.exeats.index') || request()->routeIs('boarding.exeats.show') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-door-open-line"></i> {{ __('Exeat requests') }}
                                    </a>
                                    <a href="{{ route('boarding.exeats.approvals', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.exeats.approvals') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-checkbox-circle-line"></i> {{ __('Approval queue') }}
                                    </a>
                                    <a href="{{ route('boarding.exeats.overdue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.exeats.overdue') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-time-line"></i> {{ __('Overdue returns') }}
                                    </a>
                                    <a href="{{ route('boarding.exeats.types', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.exeats.types') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-settings-line"></i> {{ __('Exeat types & quotas') }}
                                    </a>
                                    <a href="{{ route('boarding.gate.terminal', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.gate.terminal') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Gate terminal') }}
                                    </a>
                                    <a href="{{ route('boarding.gate.attempts', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.gate.attempts') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-fingerprint-line"></i> {{ __('Collection attempts') }}
                                    </a>
                                    <a href="{{ route('boarding.visitors.terminal', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.visitors.terminal') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-line"></i> {{ __('Visitor terminal') }}
                                    </a>
                                    <a href="{{ route('boarding.visitors.log', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.visitors.log') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-line"></i> {{ __('Visitor log') }}
                                    </a>
                                    <a href="{{ route('boarding.visitors.blacklist', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.visitors.blacklist') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-forbid-line"></i> {{ __('Blacklist') }}
                                    </a>
                                    <a href="{{ route('boarding.visitors.visiting-days', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.visitors.visiting-days') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-event-line"></i> {{ __('Visiting days') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Catering & kitchen') }}</div>
                                    <a href="{{ route('boarding.catering.menu-cycles', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.catering.menu-cycles') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-restaurant-line"></i> {{ __('Menu cycles') }}
                                    </a>
                                    <a href="{{ route('boarding.catering.recipes', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.catering.recipes') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-book-2-line"></i> {{ __('Recipes') }}
                                    </a>
                                    <a href="{{ route('boarding.catering.service-plan', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.catering.service-plan') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calculator-line"></i> {{ __('Daily service plan') }}
                                    </a>
                                    <a href="{{ route('boarding.catering.serving-terminal', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.catering.serving-terminal') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-scan-line"></i> {{ __('Serving terminal') }}
                                    </a>
                                    <a href="{{ route('boarding.catering.dietary', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.catering.dietary') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-heart-pulse-line"></i> {{ __('Dietary register') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Linen & laundry') }}</div>
                                    <a href="{{ route('boarding.linen.items', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.linen.items') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shirt-line"></i> {{ __('Item catalogue') }}
                                    </a>
                                    <a href="{{ route('boarding.linen.issue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.linen.issue') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-exchange-line"></i> {{ __('Issue & return') }}
                                    </a>
                                    <a href="{{ route('boarding.linen.clearance', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.linen.clearance') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Clearance') }}
                                    </a>
                                    <a href="{{ route('boarding.laundry.cycles', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.laundry.cycles') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-loop-left-line"></i> {{ __('Laundry cycles') }}
                                    </a>
                                    <a href="{{ route('boarding.laundry.missing', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('boarding.laundry.missing') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-error-warning-line"></i> {{ __('Missing items') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($sessionsSchool)
                        @php $welfareGroupActive = request()->routeIs('welfare.health.*') || request()->routeIs('welfare.behaviour.*') || request()->routeIs('welfare.sanctions.*') || request()->routeIs('welfare.detentions.*') || request()->routeIs('welfare.committee.*') || request()->routeIs('welfare.appeals.*') || request()->routeIs('welfare.leadership.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $welfareGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-welfare" aria-expanded="{{ $welfareGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-welfare">
                                <i class="ri ri-heart-pulse-line"></i> {{ __('Welfare') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $welfareGroupActive ? 'show' : '' }}" id="sidebar-group-welfare">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('Health & clinic') }} 🔒</div>
                                    <a href="{{ route('welfare.health.alerts', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.alerts') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alarm-warning-line"></i> {{ __('Alert board') }}
                                    </a>
                                    <a href="{{ route('welfare.health.sick-bay', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.sick-bay') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hotel-bed-line"></i> {{ __('Sick bay') }}
                                    </a>
                                    <a href="{{ route('welfare.health.medication-round', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.medication-round') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-capsule-line"></i> {{ __('Medication round') }}
                                    </a>
                                    <a href="{{ route('welfare.health.prescriptions', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.prescriptions') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-3-line"></i> {{ __('Prescriptions') }}
                                    </a>
                                    <a href="{{ route('welfare.health.consents', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.consents') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Consents') }}
                                    </a>
                                    <a href="{{ route('welfare.health.immunisations', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.immunisations') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-syringe-line"></i> {{ __('Immunisations') }}
                                    </a>
                                    <a href="{{ route('welfare.health.incidents', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.incidents') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-first-aid-kit-line"></i> {{ __('Incidents') }}
                                    </a>
                                    <a href="{{ route('welfare.health.referrals', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.referrals') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shuttle-line"></i> {{ __('External referrals') }}
                                    </a>
                                    <a href="{{ route('welfare.health.stock', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.stock') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stack-line"></i> {{ __('Clinic stock') }}
                                    </a>
                                    <a href="{{ route('welfare.health.outbreak', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.outbreak') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-virus-line"></i> {{ __('Outbreak monitor') }}
                                    </a>
                                    <a href="{{ route('welfare.health.screenings', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.health.screenings') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-eye-line"></i> {{ __('Screenings') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Discipline & conduct') }}</div>
                                    <a href="{{ route('welfare.behaviour.record', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.behaviour.record') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-add-line"></i> {{ __('Record behaviour') }}
                                    </a>
                                    <a href="{{ route('welfare.behaviour.board', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.behaviour.board') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-dashboard-line"></i> {{ __('Behaviour board') }}
                                    </a>
                                    <a href="{{ route('welfare.behaviour.review', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.behaviour.review') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-2"></i> {{ __('Review queue') }}
                                    </a>
                                    <a href="{{ route('welfare.behaviour.categories', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.behaviour.categories') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-price-tag-3-line"></i> {{ __('Categories') }}
                                    </a>
                                    <a href="{{ route('welfare.behaviour.rules', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.behaviour.rules') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-settings-3-line"></i> {{ __('Trigger rules') }}
                                    </a>
                                    <a href="{{ route('welfare.behaviour.analytics', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.behaviour.analytics') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-line"></i> {{ __('Analytics') }}
                                    </a>
                                    <a href="{{ route('welfare.sanctions.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.sanctions.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hammer-line"></i> {{ __('Sanctions') }}
                                    </a>
                                    <a href="{{ route('welfare.sanctions.issue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.sanctions.issue') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-gavel-line"></i> {{ __('Issue sanction') }}
                                    </a>
                                    <a href="{{ route('welfare.sanctions.types', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.sanctions.types') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-settings-line"></i> {{ __('Sanction types') }}
                                    </a>
                                    <a href="{{ route('welfare.detentions.register', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.detentions.register') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-time-line"></i> {{ __('Detention register') }}
                                    </a>
                                    <a href="{{ route('welfare.committee.hearing', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.committee.hearing') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-scales-3-line"></i> {{ __('Disciplinary committee') }}
                                    </a>
                                    <a href="{{ route('welfare.appeals.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.appeals.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-git-branch-line"></i> {{ __('Appeals') }}
                                    </a>
                                    <a href="{{ route('welfare.leadership.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.leadership.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-medal-line"></i> {{ __('Student leadership') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{--
                            Safeguarding is deliberately its own, visually
                            distinct top-level entry, not nested inside the
                            Welfare group above — Book G BRD-08 §2 ⭐⭐: the
                            one module where role grants candidacy only,
                            never access, and even the platform's own Super
                            Admin has no implicit view. The red styling and
                            lock icon are intentional, not decorative — this
                            is the single most safety-critical link in the
                            admin console and must never blend into routine
                            pastoral admin.
                        --}}
                        @php $safeguardingGroupActive = request()->routeIs('welfare.safeguarding.*') || request()->routeIs('welfare.counselling.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link text-danger fw-bold {{ $safeguardingGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-safeguarding" aria-expanded="{{ $safeguardingGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-safeguarding">
                                <i class="ri ri-shield-keyhole-line"></i> {{ __('Safeguarding') }} 🔒🔒
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $safeguardingGroupActive ? 'show' : '' }}" id="sidebar-group-safeguarding">
                                <div class="app-sidebar-subnav">
                                    <a href="{{ route('welfare.safeguarding.report', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.safeguarding.report') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-megaphone-line"></i> {{ __('Report a concern') }}
                                    </a>
                                    <a href="{{ route('welfare.safeguarding.triage', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.safeguarding.triage') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stethoscope-line"></i> {{ __('Triage queue') }}
                                    </a>
                                    <a href="{{ route('welfare.safeguarding.cases', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.safeguarding.cases') || request()->routeIs('welfare.safeguarding.case') || request()->routeIs('welfare.safeguarding.grants') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-folder-lock-line"></i> {{ __('Cases') }}
                                    </a>
                                    <a href="{{ route('welfare.safeguarding.vulnerable', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.safeguarding.vulnerable') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-plant-line"></i> {{ __('Vulnerable register') }}
                                    </a>
                                    <a href="{{ route('welfare.safeguarding.reviews', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.safeguarding.reviews') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-check-line"></i> {{ __('Overdue reviews') }}
                                    </a>
                                    <a href="{{ route('welfare.safeguarding.audit', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.safeguarding.audit') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-shield-2-line"></i> {{ __('Audit review') }}
                                    </a>
                                    <a href="{{ route('welfare.counselling.diary', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('welfare.counselling.diary') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-chat-heart-line"></i> {{ __('Counselling diary') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($sessionsSchool)
                        @php $storesGroupActive = request()->routeIs('stores.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $storesGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-stores" aria-expanded="{{ $storesGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-stores">
                                <i class="ri ri-store-2-line"></i> {{ __('Stores & Procurement') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $storesGroupActive ? 'show' : '' }}" id="sidebar-group-stores">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('Inventory & stores') }} ⭐</div>
                                    <a href="{{ route('stores.inventory.stores.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.stores.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-home-gear-line"></i> {{ __('Stores') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.items.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.items.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-barcode-box-line"></i> {{ __('Item master') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.stock.on-hand', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.stock.on-hand') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stack-line"></i> {{ __('Stock on hand') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.stock.item-ledger', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.stock.item-ledger') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-3-line"></i> {{ __('Item ledger') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.receipts.create', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.receipts.create') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-inbox-archive-line"></i> {{ __('Receive stock') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.requisitions.create', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.requisitions.create') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-add-line"></i> {{ __('New requisition') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.requisitions.issue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.requisitions.issue') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-send-plane-line"></i> {{ __('Issue requisitions') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.requisitions.return', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.requisitions.return') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-arrow-go-back-line"></i> {{ __('Returns') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.transfers.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.transfers.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-swap-line"></i> {{ __('Transfers') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.stocktake.count', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.stocktake.count') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-3"></i> {{ __('Stock take — count') }} ⭐
                                    </a>
                                    <a href="{{ route('stores.inventory.stocktake.variance', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.stocktake.variance') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-scales-3-line"></i> {{ __('Stock take variance') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.stock.expiry', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.stock.expiry') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-timer-flash-line"></i> {{ __('Expiry monitor') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.anomalies.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.anomalies.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-radar-line"></i> {{ __('Anomalies') }} ⭐
                                    </a>
                                    <a href="{{ route('stores.inventory.stock.sell-to-learner', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.stock.sell-to-learner') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-t-shirt-line"></i> {{ __('Sell to learner') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.reports.valuation', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.reports.valuation') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-coins-line"></i> {{ __('Valuation report') }}
                                    </a>
                                    <a href="{{ route('stores.inventory.reports.consumption', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.inventory.reports.consumption') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-2-line"></i> {{ __('Consumption report') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Procurement & AP') }} 🇿🇼</div>
                                    <a href="{{ route('stores.procurement.suppliers.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.suppliers.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-truck-line"></i> {{ __('Suppliers') }}
                                    </a>
                                    <a href="{{ route('stores.procurement.suppliers.clearances', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.suppliers.clearances') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Tax clearances') }}
                                    </a>
                                    <a href="{{ route('stores.procurement.requisitions.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.requisitions.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-line"></i> {{ __('Purchase requisitions') }}
                                    </a>
                                    <a href="{{ route('stores.procurement.quotations.compare', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.quotations.compare') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-git-compare-line"></i> {{ __('Quotations') }}
                                    </a>
                                    <a href="{{ route('stores.procurement.orders.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.orders.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shopping-cart-2-line"></i> {{ __('Purchase orders') }}
                                    </a>
                                    <a href="{{ route('stores.procurement.receipts.create', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.receipts.create') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-inbox-unarchive-line"></i> {{ __('Goods receipt') }}
                                    </a>
                                    <a href="{{ route('stores.procurement.invoices.register', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.invoices.register') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-text-line"></i> {{ __('Register invoice') }}
                                    </a>
                                    <a href="{{ route('stores.procurement.invoices.match', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.invoices.match') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-git-merge-line"></i> {{ __('Match review') }}
                                    </a>
                                    <a href="{{ route('stores.procurement.payments.run', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.payments.run') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bank-card-line"></i> {{ __('Payment run') }}
                                    </a>
                                    <a href="{{ route('stores.procurement.reports.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.procurement.reports.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-pie-chart-line"></i> {{ __('Procurement reports') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Fixed assets') }}</div>
                                    <a href="{{ route('stores.assets.register.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.assets.register.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-archive-drawer-line"></i> {{ __('Asset register') }}
                                    </a>
                                    <a href="{{ route('stores.assets.depreciation.run', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.assets.depreciation.run') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-line-chart-line"></i> {{ __('Depreciation run') }}
                                    </a>
                                    <a href="{{ route('stores.assets.verification.round', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.assets.verification.round') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-qr-scan-line"></i> {{ __('Verification') }} ⭐
                                    </a>
                                    <a href="{{ route('stores.assets.verification.discrepancies', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.assets.verification.discrepancies') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-error-warning-line"></i> {{ __('Discrepancies') }}
                                    </a>
                                    <a href="{{ route('stores.assets.disposal.create', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.assets.disposal.create') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-delete-bin-6-line"></i> {{ __('Disposal') }}
                                    </a>
                                    <a href="{{ route('stores.assets.insurance.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.assets.insurance.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-umbrella-line"></i> {{ __('Insurance') }}
                                    </a>
                                    <a href="{{ route('stores.assets.reports.reconciliation', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.assets.reports.reconciliation') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-scales-line"></i> {{ __('Reconciliation') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Budgeting') }} ⭐</div>
                                    <a href="{{ route('stores.budget.builder.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.budget.builder.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-line"></i> {{ __('Budget builder') }}
                                    </a>
                                    <a href="{{ route('stores.budget.consolidation.review', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.budget.consolidation.review') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stack-line"></i> {{ __('Consolidation') }}
                                    </a>
                                    <a href="{{ route('stores.budget.variance.dashboard', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.budget.variance.dashboard') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-dashboard-3-line"></i> {{ __('Variance dashboard') }}
                                    </a>
                                    <a href="{{ route('stores.budget.commitments.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.budget.commitments.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-lock-2-line"></i> {{ __('Commitments') }}
                                    </a>
                                    <a href="{{ route('stores.budget.virement.create', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.budget.virement.create') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-exchange-funds-line"></i> {{ __('Virement') }}
                                    </a>
                                    <a href="{{ route('stores.budget.forecast.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('stores.budget.forecast.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-compass-3-line"></i> {{ __('Forecasts') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($sessionsSchool)
                        @php $estatesGroupActive = request()->routeIs('transport.*') || request()->routeIs('operations.*') || request()->routeIs('farm.*') || request()->routeIs('utilities.*') || request()->routeIs('facilities.*') || request()->routeIs('security.*') || request()->routeIs('sport.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $estatesGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-estates" aria-expanded="{{ $estatesGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-estates">
                                <i class="ri ri-roadster-line"></i> {{ __('Estates & Operations') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $estatesGroupActive ? 'show' : '' }}" id="sidebar-group-estates">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('Maintenance & Works') }}</div>
                                    <a href="{{ route('operations.maintenance.report', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('operations.maintenance.report') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-tools-line"></i> {{ __('Report a fault') }}
                                    </a>
                                    <a href="{{ route('operations.maintenance.triage', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('operations.maintenance.triage') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-filter-3-line"></i> {{ __('Triage') }}
                                    </a>
                                    <a href="{{ route('operations.maintenance.work-orders.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('operations.maintenance.work-orders.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-3-line"></i> {{ __('Work orders') }}
                                    </a>
                                    <a href="{{ route('operations.maintenance.assets.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('operations.maintenance.assets.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-4-line"></i> {{ __('Maintenance assets') }}
                                    </a>
                                    <a href="{{ route('operations.maintenance.schedules.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('operations.maintenance.schedules.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-check-line"></i> {{ __('Preventive schedules') }}
                                    </a>
                                    <a href="{{ route('operations.maintenance.reports.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('operations.maintenance.reports.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-2-line"></i> {{ __('Maintenance reports') }}
                                    </a>
                                    <a href="{{ route('operations.projects.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('operations.projects.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-line"></i> {{ __('Capital projects') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Transport & Fleet') }} 🇿🇼</div>
                                    <a href="{{ route('transport.fleet.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.fleet.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bus-line"></i> {{ __('Fleet register') }}
                                    </a>
                                    <a href="{{ route('transport.compliance.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.compliance.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Compliance monitor') }} ⭐
                                    </a>
                                    <a href="{{ route('transport.drivers.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.drivers.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-id-card-line"></i> {{ __('Drivers') }}
                                    </a>
                                    <a href="{{ route('transport.routes.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.routes.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-route-line"></i> {{ __('Routes & zones') }}
                                    </a>
                                    <a href="{{ route('transport.assignment.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.assignment.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-add-line"></i> {{ __('Learner assignment') }}
                                    </a>
                                    <a href="{{ route('transport.trips.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.trips.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-event-line"></i> {{ __('Trip scheduling') }}
                                    </a>
                                    <a href="{{ route('transport.manifest.show', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.manifest.show') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-2"></i> {{ __('Driver manifest') }}
                                    </a>
                                    <a href="{{ route('transport.fuel.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.fuel.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-gas-station-line"></i> {{ __('Fuel log') }}
                                    </a>
                                    <a href="{{ route('transport.fuel-anomalies.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.fuel-anomalies.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-radar-line"></i> {{ __('Fuel anomalies') }} ⭐
                                    </a>
                                    <a href="{{ route('transport.incidents.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.incidents.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alarm-warning-line"></i> {{ __('Incidents') }}
                                    </a>
                                    <a href="{{ route('transport.route-costs.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('transport.route-costs.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-funds-line"></i> {{ __('Route costing') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Estates & Farm') }} 🇿🇼</div>
                                    <a href="{{ route('farm.units.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.units.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-plant-line"></i> {{ __('Production units') }}
                                    </a>
                                    <a href="{{ route('farm.fields.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.fields.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-map-2-line"></i> {{ __('Fields') }}
                                    </a>
                                    <a href="{{ route('farm.cycles.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.cycles.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-seedling-line"></i> {{ __('Crop cycles') }}
                                    </a>
                                    <a href="{{ route('farm.harvest.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.harvest.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-plant-fill"></i> {{ __('Harvest') }} ⭐
                                    </a>
                                    <a href="{{ route('farm.livestock.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.livestock.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-footprint-line"></i> {{ __('Livestock register') }}
                                    </a>
                                    <a href="{{ route('farm.livestock-events.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.livestock-events.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-syringe-line"></i> {{ __('Livestock events') }} ⭐
                                    </a>
                                    <a href="{{ route('farm.production.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.production.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-drop-line"></i> {{ __('Daily production') }}
                                    </a>
                                    <a href="{{ route('farm.transfers.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.transfers.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-restaurant-line"></i> {{ __('Kitchen transfers') }} ⭐
                                    </a>
                                    <a href="{{ route('farm.sales.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.sales.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shopping-basket-line"></i> {{ __('Farm sales') }}
                                    </a>
                                    <a href="{{ route('farm.reports.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('farm.reports.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-pie-chart-2-line"></i> {{ __('Farm reports') }} ⭐
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Utilities & Energy') }} 🇿🇼</div>
                                    <a href="{{ route('utilities.accounts.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.accounts.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-flashlight-line"></i> {{ __('Utility accounts') }}
                                    </a>
                                    <a href="{{ route('utilities.meters.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.meters.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-dashboard-2-line"></i> {{ __('Meters') }}
                                    </a>
                                    <a href="{{ route('utilities.tokens.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.tokens.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-coupon-3-line"></i> {{ __('Prepaid tokens') }} ⭐
                                    </a>
                                    <a href="{{ route('utilities.readings.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.readings.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-pencil-ruler-2-line"></i> {{ __('Meter readings') }}
                                    </a>
                                    <a href="{{ route('utilities.generators.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.generators.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-plug-line"></i> {{ __('Generators') }}
                                    </a>
                                    <a href="{{ route('utilities.generator-runs.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.generator-runs.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-timer-flash-line"></i> {{ __('Generator log') }}
                                    </a>
                                    <a href="{{ route('utilities.solar.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.solar.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-sun-line"></i> {{ __('Solar') }}
                                    </a>
                                    <a href="{{ route('utilities.water.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.water.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-drop-line"></i> {{ __('Water') }}
                                    </a>
                                    <a href="{{ route('utilities.load-shedding.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.load-shedding.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-flashlight-line"></i> {{ __('Load shedding') }}
                                    </a>
                                    <a href="{{ route('utilities.dashboard.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('utilities.dashboard.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-grouped-line"></i> {{ __('Energy dashboard') }} ⭐
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Facilities & Hire') }}</div>
                                    <a href="{{ route('facilities.resources.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('facilities.resources.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-2-line"></i> {{ __('Bookable resources') }}
                                    </a>
                                    <a href="{{ route('facilities.calendar.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('facilities.calendar.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-2-line"></i> {{ __('Resource calendar') }}
                                    </a>
                                    <a href="{{ route('facilities.request.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('facilities.request.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-check-line"></i> {{ __('Booking request') }}
                                    </a>
                                    <a href="{{ route('facilities.hire.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('facilities.hire.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hand-coin-line"></i> {{ __('External hire') }}
                                    </a>
                                    <a href="{{ route('facilities.utilisation.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('facilities.utilisation.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-pie-chart-line"></i> {{ __('Utilisation') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Security & Access') }}</div>
                                    <a href="{{ route('security.muster.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('security.muster.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-voice-line"></i> {{ __('Muster roll') }} ⭐
                                    </a>
                                    <a href="{{ route('security.occurrence-book.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('security.occurrence-book.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-book-2-line"></i> {{ __('Occurrence book') }}
                                    </a>
                                    <a href="{{ route('security.patrols.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('security.patrols.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-walk-line"></i> {{ __('Patrols') }}
                                    </a>
                                    <a href="{{ route('security.contractors.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('security.contractors.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hard-hat-line"></i> {{ __('Contractors & gate') }} ⭐
                                    </a>
                                    <a href="{{ route('security.keys.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('security.keys.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-key-2-line"></i> {{ __('Keys & cards') }}
                                    </a>
                                    <a href="{{ route('security.lost-property.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('security.lost-property.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-briefcase-4-line"></i> {{ __('Lost property') }}
                                    </a>
                                    <a href="{{ route('security.drills.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('security.drills.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alarm-warning-line"></i> {{ __('Emergency drills') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Sport, Houses & Co-curricular') }}</div>
                                    <a href="{{ route('sport.activities.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('sport.activities.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-football-line"></i> {{ __('Activities') }}
                                    </a>
                                    <a href="{{ route('sport.membership.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('sport.membership.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-team-line"></i> {{ __('Membership') }}
                                    </a>
                                    <a href="{{ route('sport.teams.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('sport.teams.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-star-line"></i> {{ __('Teams') }}
                                    </a>
                                    <a href="{{ route('sport.fixtures.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('sport.fixtures.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-event-line"></i> {{ __('Fixtures') }} ⭐
                                    </a>
                                    <a href="{{ route('sport.houses.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('sport.houses.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-trophy-line"></i> {{ __('House leaderboard') }}
                                    </a>
                                    <a href="{{ route('sport.awards.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('sport.awards.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-medal-line"></i> {{ __('Awards') }}
                                    </a>
                                    <a href="{{ route('sport.equipment.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('sport.equipment.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shirt-line"></i> {{ __('Equipment') }}
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

                                    <div class="app-sidebar-heading">{{ __('Discounts & scholarships') }}</div>
                                    <a href="{{ route('finance.discounts.schemes', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.discounts.schemes') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-price-tag-3-line"></i> {{ __('Discount schemes') }}
                                    </a>
                                    <a href="{{ route('finance.discounts.budgets', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.discounts.budgets') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-pie-chart-2-line"></i> {{ __('Budget envelopes') }}
                                    </a>
                                    <a href="{{ route('finance.scholarships.applications', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.scholarships.applications') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-3-line"></i> {{ __('Applications') }}
                                    </a>
                                    <a href="{{ route('finance.scholarships.committee', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.scholarships.committee') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-team-line"></i> {{ __('Committee review') }}
                                    </a>
                                    <a href="{{ route('finance.awards.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.awards.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-award-line"></i> {{ __('Awards') }}
                                    </a>
                                    <a href="{{ route('finance.awards.grant', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.awards.grant') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-add-circle-line"></i> {{ __('Grant award') }}
                                    </a>
                                    <a href="{{ route('finance.awards.condition-review', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.awards.condition-review') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-checkbox-multiple-line"></i> {{ __('Condition review') }}
                                    </a>
                                    <a href="{{ route('finance.awards.sponsors', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.awards.sponsors') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-building-line"></i> {{ __('Sponsor awards') }}
                                    </a>
                                    <a href="{{ route('finance.reports.discounts', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('finance.reports.discounts') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-grouped-line"></i> {{ __('Cost of generosity') }}
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

                    @if ($sessionsSchool)
                        @php $payrollComplianceGroupActive = request()->routeIs('payroll.*') || request()->routeIs('fiscal.*') || request()->routeIs('wallet.*') || request()->routeIs('reporting.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $payrollComplianceGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-payroll-compliance" aria-expanded="{{ $payrollComplianceGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-payroll-compliance">
                                <i class="ri ri-bank-card-line"></i> {{ __('Payroll & Compliance') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $payrollComplianceGroupActive ? 'show' : '' }}" id="sidebar-group-payroll-compliance">
                                <div class="app-sidebar-subnav">
                                    {{--
                                        Book H3 — Payroll, Fiscalisation & Compliance. Grouped by
                                        the four Laravel modules this book's PPL-05/FIN-13/FIN-14/
                                        FIN-12 passes shipped into (Payroll/Fiscal/Wallet/Reporting),
                                        matching the sub-heading convention `Estates & Operations`
                                        above already uses. CMP-01–04 (Modules\Compliance) are a
                                        separate, not-yet-started pass — nothing added here for them.
                                    --}}
                                    <div class="app-sidebar-heading">{{ __('Payroll') }} 🇿🇼</div>
                                    <a href="{{ route('payroll.statutory.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.statutory.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-government-line"></i> {{ __('Statutory configuration') }}
                                    </a>
                                    <a href="{{ route('payroll.grades.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.grades.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stairs-line"></i> {{ __('Pay grades') }}
                                    </a>
                                    <a href="{{ route('payroll.components.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.components.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-settings-line"></i> {{ __('Pay components') }}
                                    </a>
                                    <a href="{{ route('payroll.staff.structure', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.staff.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-settings-line"></i> {{ __('Staff pay structure') }}
                                    </a>
                                    <a href="{{ route('payroll.loans.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.loans.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hand-coin-line"></i> {{ __('Staff loans') }}
                                    </a>
                                    <a href="{{ route('payroll.run.wizard', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.run.wizard') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-play-circle-line"></i> {{ __('Payroll run') }} ⭐
                                    </a>
                                    <a href="{{ route('payroll.run.bank-file', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.run.bank-file') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bank-line"></i> {{ __('Bank file') }}
                                    </a>
                                    <a href="{{ route('payroll.returns.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.returns.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-text-line"></i> {{ __('Statutory returns') }}
                                    </a>
                                    <a href="{{ route('payroll.returns.itf16', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.returns.itf16') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-chart-line"></i> {{ __('ITF16 reconciliation') }}
                                    </a>
                                    <a href="{{ route('payroll.reports.summary', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('payroll.reports.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-2-line"></i> {{ __('Payroll reports') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('ZIMRA Fiscalisation') }} 🇿🇼</div>
                                    <a href="{{ route('fiscal.devices.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('fiscal.devices.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hard-drive-2-line"></i> {{ __('Fiscal devices') }}
                                    </a>
                                    <a href="{{ route('fiscal.rules.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('fiscal.rules.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-route-line"></i> {{ __('Routing rules') }}
                                    </a>
                                    <a href="{{ route('fiscal.days.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('fiscal.days.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-event-line"></i> {{ __('Fiscal day control') }}
                                    </a>
                                    <a href="{{ route('fiscal.receipts.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('fiscal.receipts.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-receipt-line"></i> {{ __('Fiscal receipts') }}
                                    </a>
                                    <a href="{{ route('fiscal.receipts.retry', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('fiscal.receipts.retry') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-refresh-line"></i> {{ __('Retry workbench') }} ⭐
                                    </a>
                                    <a href="{{ route('fiscal.queue.status', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('fiscal.queue.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-stack-line"></i> {{ __('Offline queue') }}
                                    </a>
                                    <a href="{{ route('fiscal.reports.z-reports', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('fiscal.reports.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-3-line"></i> {{ __('Z-reports') }}
                                    </a>
                                    <a href="{{ route('fiscal.reconciliation.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('fiscal.reconciliation.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-git-merge-line"></i> {{ __('Reconciliation') }} ⭐
                                    </a>
                                    <a href="{{ route('fiscal.audit.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('fiscal.audit.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-shield-2-line"></i> {{ __('Audit log') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Student Wallet & Tuckshop') }}</div>
                                    <a href="{{ route('wallet.pos.terminal', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('wallet.pos.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shopping-cart-2-line"></i> {{ __('Tuckshop POS') }} ⭐
                                    </a>
                                    <a href="{{ route('wallet.products.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('wallet.products.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-price-tag-3-line"></i> {{ __('Products') }}
                                    </a>
                                    <a href="{{ route('wallet.spend-points.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('wallet.spend-points.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-store-2-line"></i> {{ __('Spend points') }}
                                    </a>
                                    <a href="{{ route('wallet.wallets.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('wallet.wallets.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-wallet-3-line"></i> {{ __('Student wallets') }}
                                    </a>
                                    <a href="{{ route('wallet.term-end.process', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('wallet.term-end.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-close-line"></i> {{ __('Term-end processing') }} ⚠
                                    </a>
                                    <a href="{{ route('wallet.reports.reconciliation', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('wallet.reports.reconciliation') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-scales-line"></i> {{ __('Wallet reconciliation') }} ⭐
                                    </a>
                                    <a href="{{ route('wallet.reports.sales', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('wallet.reports.sales') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-line-chart-line"></i> {{ __('Sales analytics') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Financial Reporting & Close') }} ⭐</div>
                                    <a href="{{ route('reporting.trial-balance', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('reporting.trial-balance') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-balance-line"></i> {{ __('Trial balance') }}
                                    </a>
                                    <a href="{{ route('reporting.income-statement', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('reporting.income-statement') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-funds-line"></i> {{ __('Income statement') }}
                                    </a>
                                    <a href="{{ route('reporting.close-checklist', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('reporting.close-checklist') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-checkbox-multiple-line"></i> {{ __('Close checklist') }}
                                    </a>
                                    <a href="{{ route('reporting.schedules.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('reporting.schedules.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-schedule-line"></i> {{ __('Scheduled reports') }}
                                    </a>
                                    <a href="{{ route('reporting.export.accounting', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('reporting.export.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-download-2-line"></i> {{ __('Accounting export') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($sessionsSchool)
                        {{--
                            Book H3 CMP-01–04 (Modules\Compliance), the four modules
                            left not-yet-started by the Payroll & Compliance group
                            above (that group's own comment names them explicitly).
                            A separate top-level group rather than a fifth heading
                            inside "Payroll & Compliance" — ZIMSEC/MoPSE/data
                            protection/policy registers aren't payroll or
                            fiscalisation concerns, and `compliance.*` route names
                            are already distinct from `payroll.*`/`fiscal.*`/
                            `wallet.*`/`reporting.*`, so there is no prefix overlap
                            to worry about.
                        --}}
                        @php $complianceGroupActive = request()->routeIs('compliance.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $complianceGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-compliance" aria-expanded="{{ $complianceGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-compliance">
                                <i class="ri ri-shield-check-line"></i> {{ __('Regulatory Compliance') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $complianceGroupActive ? 'show' : '' }}" id="sidebar-group-compliance">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('ZIMSEC') }} 🇿🇼</div>
                                    <a href="{{ route('compliance.zimsec.registrations.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.zimsec.registrations.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-list-line"></i> {{ __('Registrations') }}
                                    </a>
                                    <a href="{{ route('compliance.zimsec.validation.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.zimsec.validation.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-check-line"></i> {{ __('Candidate validation') }} ⭐
                                    </a>
                                    <a href="{{ route('compliance.zimsec.fees.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.zimsec.fees.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-money-dollar-circle-line"></i> {{ __('Entry fees') }}
                                    </a>
                                    <a href="{{ route('compliance.zimsec.export.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.zimsec.export.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-upload-2-line"></i> {{ __('Export & submission') }} ⚠
                                    </a>
                                    <a href="{{ route('compliance.zimsec.statements.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.zimsec.statements.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-mail-send-line"></i> {{ __('Statements of entry') }}
                                    </a>
                                    <a href="{{ route('compliance.zimsec.results-import.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.zimsec.results-import.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-upload-line"></i> {{ __('Results import') }}
                                    </a>
                                    <a href="{{ route('compliance.zimsec.analysis.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.zimsec.analysis.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-grouped-line"></i> {{ __('Pass-rate analysis') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('MoPSE / EMIS') }} 🇿🇼</div>
                                    <a href="{{ route('compliance.mopse.returns.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.mopse.returns.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-government-line"></i> {{ __('Statutory returns') }}
                                    </a>
                                    <a href="{{ route('compliance.mopse.inspection-pack.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.mopse.inspection-pack.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-folder-received-line"></i> {{ __('Inspection pack') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Data Protection & Privacy') }} 🇿🇼</div>
                                    <a href="{{ route('compliance.privacy.consent-types.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.privacy.consent-types.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-2"></i> {{ __('Consent types') }}
                                    </a>
                                    <a href="{{ route('compliance.privacy.consents.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.privacy.consents.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-follow-line"></i> {{ __('Consent register') }}
                                    </a>
                                    <a href="{{ route('compliance.privacy.retention.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.privacy.retention.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-time-line"></i> {{ __('Retention schedules') }} ⚠
                                    </a>
                                    <a href="{{ route('compliance.privacy.disposal.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.privacy.disposal.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-delete-bin-6-line"></i> {{ __('Disposal queue') }} ⚠⚠
                                    </a>
                                    <a href="{{ route('compliance.privacy.requests.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.privacy.requests.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-search-line"></i> {{ __('Subject access requests') }} ⚠
                                    </a>
                                    <a href="{{ route('compliance.privacy.breaches.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.privacy.breaches.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alarm-warning-line"></i> {{ __('Breach register') }} ⚠⚠
                                    </a>
                                    <a href="{{ route('compliance.privacy.processing.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.privacy.processing.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-flow-chart"></i> {{ __('Processing register') }}
                                    </a>
                                    <a href="{{ route('compliance.privacy.processors.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.privacy.processors.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-cloud-line"></i> {{ __('Third-party processors') }}
                                    </a>
                                    <a href="{{ route('compliance.privacy.notices.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.privacy.notices.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-text-line"></i> {{ __('Privacy notices') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Policy & Document Register') }}</div>
                                    <a href="{{ route('compliance.policy.policies.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.policy.policies.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-article-line"></i> {{ __('Policy register') }}
                                    </a>
                                    <a href="{{ route('compliance.policy.documents.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.policy.documents.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-shield-2-line"></i> {{ __('Statutory documents & contracts') }}
                                    </a>
                                    <a href="{{ route('compliance.policy.minutes.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.policy.minutes.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-book-2-line"></i> {{ __('Governance minutes') }}
                                    </a>
                                    <a href="{{ route('compliance.policy.incident-register.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('compliance.policy.incident-register.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alert-line"></i> {{ __('Incident register') }} ⭐
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{--
                            Book I (Modules\Comms) admin screens. COM-01, COM-02,
                            COM-03/04/05's admin screen, COM-06, COM-07 and COM-08 — all of Book I —
                            are built; later COM modules add their own
                            headings inside this one group.
                        --}}
                        @php $commsGroupActive = request()->routeIs('comms.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $commsGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-comms" aria-expanded="{{ $commsGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-comms">
                                <i class="ri ri-chat-3-line"></i> {{ __('Communication') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $commsGroupActive ? 'show' : '' }}" id="sidebar-group-comms">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('Messaging Gateways') }} 🇿🇼</div>
                                    <a href="{{ route('comms.gateways.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.gateways.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-router-line"></i> {{ __('Gateways') }} ⚠⚠
                                    </a>
                                    <a href="{{ route('comms.whatsapp.templates', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.whatsapp.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-whatsapp-line"></i> {{ __('WhatsApp templates') }} ⭐
                                    </a>
                                    <a href="{{ route('comms.sms.sender-ids', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.sms.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-message-2-line"></i> {{ __('SMS sender IDs') }}
                                    </a>
                                    <a href="{{ route('comms.reports.cost', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.reports.cost') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-2-line"></i> {{ __('Cost & segmentation') }}
                                    </a>
                                    <a href="{{ route('comms.reports.reconciliation', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.reports.reconciliation') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-scales-3-line"></i> {{ __('Cost reconciliation') }} ⭐
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Automation Rules') }}</div>
                                    <a href="{{ route('comms.automation.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.automation.index', 'comms.automation.create', 'comms.automation.builder') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-robot-2-line"></i> {{ __('Rule library') }} ⭐
                                    </a>
                                    <a href="{{ route('comms.automation.executions', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.automation.executions') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-list-check-3"></i> {{ __('Execution log') }}
                                    </a>
                                    <a href="{{ route('comms.automation.scans', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.automation.scans') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-radar-line"></i> {{ __('Scan history') }}
                                    </a>
                                    <a href="{{ route('comms.automation.variants', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.automation.variants') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-test-tube-line"></i> {{ __('A/B performance') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Portals') }}</div>
                                    <a href="{{ route('comms.portal.widgets', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.portal.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-layout-grid-line"></i> {{ __('Portal widgets') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Calendar, Notices & Events') }}</div>
                                    <a href="{{ route('comms.calendar.view', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.calendar.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-2-line"></i> {{ __('Calendar') }}
                                    </a>
                                    <a href="{{ route('comms.notices.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.notices.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-pushpin-line"></i> {{ __('Notice board') }}
                                    </a>
                                    <a href="{{ route('comms.notices.compose', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.notices.compose') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-megaphone-line"></i> {{ __('Post notice') }} ⚠
                                    </a>
                                    <a href="{{ route('comms.newsletters.compose', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.newsletters.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-newspaper-line"></i> {{ __('Newsletters') }}
                                    </a>
                                    <a href="{{ route('comms.events.register', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.events.register') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-ticket-2-line"></i> {{ __('Event registration') }} ⚠
                                    </a>
                                    <a href="{{ route('comms.events.checkin', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.events.checkin') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-qr-scan-2-line"></i> {{ __('Event check-in') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Virtual Meetings') }}</div>
                                    <a href="{{ route('comms.meetings.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.meetings.index') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-vidicon-line"></i> {{ __('Meeting schedule') }}
                                    </a>
                                    <a href="{{ route('comms.meetings.consultations', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.meetings.consultations') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-voice-line"></i> {{ __('Consultation windows') }}
                                    </a>
                                    <a href="{{ route('comms.meetings.attendance', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.meetings.attendance') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-user-follow-line"></i> {{ __('Online attendance') }} ⭐
                                    </a>
                                    <a href="{{ route('comms.meetings.recordings', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.meetings.recordings') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-record-circle-line"></i> {{ __('Recordings') }}
                                    </a>
                                    <a href="{{ route('comms.meetings.providers', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.meetings.providers') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-plug-line"></i> {{ __('Meeting providers') }} ⚠⚠
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Feedback & Complaints') }}</div>
                                    <a href="{{ route('comms.surveys.builder', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.surveys.*') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-survey-line"></i> {{ __('Surveys') }}
                                    </a>
                                    <a href="{{ route('comms.complaints.submit', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.complaints.submit') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-feedback-line"></i> {{ __('Raise a complaint') }}
                                    </a>
                                    <a href="{{ route('comms.complaints.queue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.complaints.queue', 'comms.complaints.show', 'comms.complaints.categories') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-inbox-line"></i> {{ __('Complaint queue') }} ⚠
                                    </a>
                                    <a href="{{ route('comms.exit-interviews', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('comms.exit-interviews') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-logout-box-r-line"></i> {{ __('Exit interviews') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{--
                            Book J (Modules\Intelligence) — school-facing
                            INT screens only. The vendor-facing SAA modules are
                            a separate authentication realm and never appear in
                            this sidebar (Book J §0.2).
                        --}}
                        @php $insightsGroupActive = request()->routeIs('insights.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $insightsGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-insights" aria-expanded="{{ $insightsGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-insights">
                                <i class="ri ri-bar-chart-box-line"></i> {{ __('Insights') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $insightsGroupActive ? 'show' : '' }}" id="sidebar-group-insights">
                                <div class="app-sidebar-subnav">
                                    <div class="app-sidebar-heading">{{ __('Executive') }}</div>
                                    <a href="{{ route('insights.executive.head', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.executive.head') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-dashboard-3-line"></i> {{ __('Head dashboard') }}
                                    </a>
                                    <a href="{{ route('insights.executive.bursar', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.executive.bursar') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-money-dollar-box-line"></i> {{ __('Bursar dashboard') }}
                                    </a>
                                    <a href="{{ route('insights.executive.kpis', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.executive.kpis') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-focus-3-line"></i> {{ __('KPI targets') }}
                                    </a>
                                    <a href="{{ route('insights.executive.board-pack', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.executive.board-pack') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-presentation-line"></i> {{ __('Board pack') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Early warning') }}</div>
                                    <a href="{{ route('insights.early-warning.queue', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.early-warning.queue', 'insights.early-warning.student') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-alarm-warning-line"></i> {{ __('At-risk queue') }}
                                    </a>
                                    <a href="{{ route('insights.early-warning.fee-risk', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.early-warning.fee-risk') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-hand-coin-line"></i> {{ __('Fee default risk') }}
                                    </a>
                                    <a href="{{ route('insights.early-warning.enrolment', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.early-warning.enrolment') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-line-chart-line"></i> {{ __('Enrolment forecast') }}
                                    </a>
                                    <a href="{{ route('insights.early-warning.staff-wellbeing', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.early-warning.staff-wellbeing') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-heart-pulse-line"></i> {{ __('Staff wellbeing') }}
                                    </a>
                                    <a href="{{ route('insights.early-warning.weights', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.early-warning.weights') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-equalizer-line"></i> {{ __('Indicator weights') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Integrations') }}</div>
                                    <a href="{{ route('insights.integrations.clients', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.integrations.clients') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-key-2-line"></i> {{ __('API clients') }}
                                    </a>
                                    <a href="{{ route('insights.integrations.webhooks', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.integrations.webhooks') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-webhook-line"></i> {{ __('Webhooks') }}
                                    </a>
                                    <a href="{{ route('insights.integrations.webhooks.log', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.integrations.webhooks.log') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-history-line"></i> {{ __('Delivery log') }}
                                    </a>
                                    <a href="{{ route('insights.integrations.sso', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.integrations.sso') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-shield-user-line"></i> {{ __('SSO') }}
                                    </a>
                                    <a href="{{ route('insights.integrations.hardware', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.integrations.hardware') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-router-line"></i> {{ __('Hardware devices') }}
                                    </a>
                                    <a href="{{ route('insights.integrations.usage', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.integrations.usage') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bar-chart-line"></i> {{ __('API usage') }}
                                    </a>

                                    <div class="app-sidebar-heading">{{ __('Custom reports') }}</div>
                                    <a href="{{ route('insights.reports.index', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.reports.index', 'insights.reports.shared') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-file-chart-line"></i> {{ __('My reports') }}
                                    </a>
                                    <a href="{{ route('insights.reports.builder', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.reports.builder') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-tools-line"></i> {{ __('Report builder') }} ⭐
                                    </a>
                                    <a href="{{ route('insights.reports.schedule', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.reports.schedule') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-calendar-schedule-line"></i> {{ __('Scheduled reports') }}
                                    </a>
                                    <a href="{{ route('insights.reports.executions', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('insights.reports.executions') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-history-line"></i> {{ __('Execution log') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- Book J SAA-01/03 school-facing entries only (this tenant's own subscription, support and help). The vendor console is a separate realm and is never linked from here. --}}
                        @php $accountGroupActive = request()->routeIs('account.*'); @endphp
                        <div class="app-sidebar-group">
                            <a href="javascript:void(0)" class="nav-link app-sidebar-toggle-link {{ $accountGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#sidebar-group-account" aria-expanded="{{ $accountGroupActive ? 'true' : 'false' }}" aria-controls="sidebar-group-account">
                                <i class="ri ri-vip-crown-2-line"></i> {{ __('Account') }}
                                <i class="ri ri-arrow-right-s-line ms-auto app-sidebar-caret"></i>
                            </a>
                            <div class="collapse {{ $accountGroupActive ? 'show' : '' }}" id="sidebar-group-account">
                                <div class="app-sidebar-subnav">
                                    <a href="{{ route('account.subscription', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('account.subscription') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-bank-card-line"></i> {{ __('My subscription') }}
                                    </a>
                                    <a href="{{ route('account.announcements', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('account.announcements') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-megaphone-line"></i> {{ __('Announcements') }}
                                    </a>
                                    <a href="{{ route('account.support', $sessionsSchool) }}" class="nav-link {{ request()->routeIs('account.support') ? 'active' : '' }}" wire:navigate>
                                        <i class="ri ri-customer-service-2-line"></i> {{ __('Contact support') }}
                                    </a>
                                    <a href="{{ route('help.index') }}" class="nav-link" wire:navigate>
                                        <i class="ri ri-question-line"></i> {{ __('Help articles') }}
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
