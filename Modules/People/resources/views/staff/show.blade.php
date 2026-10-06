<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ $staff->fullName() }}</h4>
            <p class="text-body-secondary mb-0">
                {{ $staff->staff_number }} ·
                <span class="badge {{ $staff->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ str_replace('_', ' ', ucfirst($staff->status)) }}</span>
                · {{ ucfirst($staff->staff_category) }}@if ($staff->department) — {{ $staff->department->name }}@endif
            </p>
        </div>
        <a href="{{ route('people.staff.contracts', [$school, $staff]) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Contracts') }}</a>
        <a href="{{ route('people.staff.qualifications', [$school, $staff]) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Qualifications') }}</a>
        <a href="{{ route('people.staff.disciplinary', [$school, $staff]) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Disciplinary') }}</a>
        <a href="{{ route('people.staff.exit', [$school, $staff]) }}" class="btn btn-outline-warning" wire:navigate>{{ __('Exit') }}</a>
    </div>

    <ul class="nav nav-tabs mb-4 flex-wrap">
        @foreach (['personal' => __('Personal'), 'employment' => __('Employment'), 'allocations' => __('Allocations'), 'workload' => __('Workload'), 'leave' => __('Leave'), 'duties' => __('Duties'), 'appraisal' => __('Appraisal'), 'documents' => __('Documents')] as $tab => $label)
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === $tab ? 'active' : '' }}" wire:click="setTab('{{ $tab }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    @if ($activeTab === 'personal')
        <div class="card">
            <div class="card-header">{{ __('Personal details') }}</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-3">{{ __('Date of birth') }}</dt><dd class="col-9">{{ $staff->date_of_birth->format('d M Y') }}</dd>
                    <dt class="col-3">{{ __('Gender') }}</dt><dd class="col-9">{{ ucfirst($staff->gender) }}</dd>
                    <dt class="col-3">{{ __('Nationality') }}</dt><dd class="col-9">{{ $staff->nationality }}</dd>
                    <dt class="col-3">{{ __('Primary phone') }}</dt><dd class="col-9">{{ $staff->primary_phone }}</dd>
                    <dt class="col-3">{{ __('Personal email') }}</dt><dd class="col-9">{{ $staff->personal_email ?? '—' }}</dd>
                    <dt class="col-3">{{ __('Work email') }}</dt><dd class="col-9">{{ $staff->work_email ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    @elseif ($activeTab === 'employment')
        <div class="card">
            <div class="card-header">{{ __('Employment') }}</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-3">{{ __('Joined on') }}</dt><dd class="col-9">{{ $staff->joined_on->format('d M Y') }}</dd>
                    <dt class="col-3">{{ __('Confirmed on') }}</dt><dd class="col-9">{{ $staff->confirmed_on?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-3">{{ __('Department') }}</dt><dd class="col-9">{{ $staff->department?->name ?? '—' }}</dd>
                    <dt class="col-3">{{ __('Post') }}</dt><dd class="col-9">{{ $staff->post?->title ?? '—' }}</dd>
                    <dt class="col-3">{{ __('Reports to') }}</dt><dd class="col-9">{{ $staff->reportsTo?->fullName() ?? '—' }}</dd>
                    <dt class="col-3">{{ __('Teaching') }}</dt><dd class="col-9">{{ $staff->is_teaching ? __('Yes') : __('No') }}</dd>
                    @if ($staff->is_teaching)
                        <dt class="col-3">{{ __('Max weekly periods') }}</dt><dd class="col-9">{{ $staff->max_weekly_periods ?? __('School default') }}</dd>
                    @endif
                </dl>

                @if ($this->canViewCompensation())
                    @php $contract = $staff->activeContract(); @endphp
                    <hr>
                    <h6>{{ __('Active contract') }}</h6>
                    @if ($contract)
                        <dl class="row mb-0">
                            <dt class="col-3">{{ __('Type') }}</dt><dd class="col-9">{{ ucfirst($contract->contract_type) }}</dd>
                            <dt class="col-3">{{ __('Salary') }}</dt><dd class="col-9">{{ $contract->salary_currency }} {{ $contract->basic_salary_minor !== null ? number_format($contract->basic_salary_minor / 100, 2) : '—' }}</dd>
                            <dt class="col-3">{{ __('Bank') }}</dt><dd class="col-9">{{ $staff->bank_name ?? '—' }} {{ $staff->bank_account_number ? '— '.$staff->bank_account_number : '' }}</dd>
                        </dl>
                    @else
                        <p class="text-body-secondary mb-0">{{ __('No active contract.') }}</p>
                    @endif
                @else
                    <p class="text-body-secondary small mt-3 mb-0">{{ __('Salary and banking details require the view-compensation permission.') }}</p>
                @endif
            </div>
        </div>
    @elseif ($activeTab === 'allocations')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                {{ __('Active teaching allocations') }}
                <a href="{{ route('people.allocation.matrix', $school) }}" class="btn btn-sm btn-outline-primary" wire:navigate>{{ __('Allocation matrix') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Class') }}</th><th>{{ __('Role') }}</th><th>{{ __('Weekly periods') }}</th><th>{{ __('Class teacher') }}</th></tr></thead>
                    <tbody>
                        @forelse ($allocations as $allocation)
                            <tr>
                                <td>{{ $allocation->subject?->name }}</td>
                                <td>{{ $allocation->schoolClass?->name }}</td>
                                <td>{{ ucfirst($allocation->role) }}</td>
                                <td>{{ $allocation->weekly_periods }}</td>
                                <td>{{ $allocation->is_class_teacher ? __('Yes') : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No active allocations.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($activeTab === 'workload')
        <div class="card">
            <div class="card-header">{{ __('Workload by term') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Term') }}</th><th>{{ __('Periods') }}</th><th>{{ __('Subjects') }}</th><th>{{ __('Classes') }}</th><th>{{ __('Utilisation') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($workloads as $workload)
                            <tr>
                                <td>{{ $workload->term_id }}</td>
                                <td>{{ $workload->teaching_periods }}</td>
                                <td>{{ $workload->subject_count }}</td>
                                <td>{{ $workload->class_count }}</td>
                                <td>{{ $workload->utilisation_percent ?? '—' }}%</td>
                                <td>@if ($workload->is_overloaded) <span class="badge text-bg-danger">{{ __('Overloaded') }}</span> @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No workload recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($activeTab === 'leave')
        <div class="row g-4">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        {{ __('Leave balances') }}
                        <a href="{{ route('people.leave.request', $school) }}" class="btn btn-sm btn-outline-primary" wire:navigate>{{ __('Request leave') }}</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Available') }}</th><th>{{ __('Pending') }}</th><th>{{ __('Taken') }}</th></tr></thead>
                            <tbody>
                                @forelse ($leaveBalances as $balance)
                                    <tr>
                                        <td>{{ $balance->leaveType?->name }}</td>
                                        <td>{{ $balance->available_days }}</td>
                                        <td>{{ $balance->pending_days }}</td>
                                        <td>{{ $balance->taken_days }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No leave balances yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">{{ __('Recent leave requests') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Days') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                                @forelse ($leaveRequests as $request)
                                    <tr>
                                        <td>{{ $request->leaveType?->name }}</td>
                                        <td>{{ $request->starts_on->format('d M') }} – {{ $request->ends_on->format('d M Y') }}</td>
                                        <td>{{ $request->working_days }}</td>
                                        <td><span class="badge text-bg-secondary">{{ ucfirst($request->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No leave requests yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($activeTab === 'duties')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                {{ __('Recent duties') }}
                <a href="{{ route('people.duty.rosters', $school) }}" class="btn btn-sm btn-outline-primary" wire:navigate>{{ __('Duty rosters') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Roster') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($dutyAssignments as $assignment)
                            <tr>
                                <td>{{ $assignment->roster?->name }}</td>
                                <td>{{ $assignment->starts_at->format('d M Y H:i') }}</td>
                                <td>{{ $assignment->ends_at->format('d M Y H:i') }}</td>
                                <td><span class="badge text-bg-secondary">{{ ucfirst($assignment->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No duties assigned yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($activeTab === 'appraisal')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                {{ __('Appraisals') }}
                <a href="{{ route('people.appraisal.index', $school) }}" class="btn btn-sm btn-outline-primary" wire:navigate>{{ __('Appraisal cycle') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Cycle') }}</th><th>{{ __('Year') }}</th><th>{{ __('Status') }}</th><th>{{ __('Rating') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($appraisals as $appraisal)
                            <tr>
                                <td>{{ ucfirst($appraisal->cycle) }}</td>
                                <td>{{ $appraisal->academic_year_id }}</td>
                                <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', ucfirst($appraisal->status)) }}</span></td>
                                <td>{{ $appraisal->overall_rating ?? '—' }}</td>
                                <td class="text-end"><a href="{{ route('people.appraisal.show', [$school, $appraisal]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Open') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No appraisals yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($activeTab === 'documents')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                {{ __('Documents') }}
                <a href="{{ route('people.staff.compliance', $school) }}" class="btn btn-sm btn-outline-primary" wire:navigate>{{ __('Compliance dashboard') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Verified') }}</th></tr></thead>
                    <tbody>
                        @forelse ($documents as $document)
                            <tr>
                                <td>{{ str_replace('_', ' ', ucfirst($document->document_type)) }}</td>
                                <td>{{ $document->reference_number ?? '—' }}</td>
                                <td>{{ $document->expires_on?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $document->is_verified ? __('Yes') : __('No') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No documents on file.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
