<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.billing.history', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Billing run #:id', ['id' => $billingRun->id]) }}</h4>
            <p class="text-body-secondary mb-0">
                {{ __(':computed of :total learners computed, :exceptions exception(s).', ['computed' => $billingRun->computed_count, 'total' => $billingRun->total_learners, 'exceptions' => $billingRun->exception_count]) }}
            </p>
        </div>
        <span class="badge fs-6 {{ match ($billingRun->status) { 'committed' => 'text-bg-success', 'approved' => 'text-bg-info', 'preview' => 'text-bg-warning', default => 'text-bg-secondary' } }}">
            {{ \Illuminate\Support\Str::headline($billingRun->status) }}
        </span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <div class="small text-body-secondary">{{ __('Total gross') }}</div>
                <div class="fs-5">{{ number_format($billingRun->total_gross_minor / 100, 2) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <div class="small text-body-secondary">{{ __('Total discount') }}</div>
                <div class="fs-5">{{ number_format($billingRun->total_discount_minor / 100, 2) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <div class="small text-body-secondary">{{ __('Total net') }}</div>
                <div class="fs-5">{{ number_format($billingRun->total_net_minor / 100, 2) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body d-flex flex-column gap-2">
                @if ($this->canApprove())
                    <button type="button" class="btn btn-sm btn-success" wire:click="approve" wire:confirm="{{ __('Approve this billing run?') }}">{{ __('Approve') }}</button>
                @endif
                @if ($this->canCommit())
                    <button type="button" class="btn btn-sm btn-primary" wire:click="commit" wire:confirm="{{ __('Commit this run? This raises invoices and posts journals — it cannot be un-run.') }}">{{ __('Commit') }}</button>
                @endif
                @if (! $this->canApprove() && ! $this->canCommit())
                    <span class="text-body-secondary small">{{ __('No further action available to you at this stage.') }}</span>
                @endif
            </div></div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'learners' ? 'active' : '' }}" wire:click="$set('activeTab', 'learners')">{{ __('Learners') }}</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'exceptions' ? 'active' : '' }}" wire:click="$set('activeTab', 'exceptions')">
                {{ __('Exceptions') }} <span class="badge text-bg-danger">{{ $billingRun->exception_count }}</span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'variance' ? 'active' : '' }}" wire:click="$set('activeTab', 'variance')">{{ __('Variance') }}</button>
        </li>
    </ul>

    @if ($activeTab === 'learners')
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Learner') }}</th>
                            <th>{{ __('Structure') }}</th>
                            <th class="text-end">{{ __('Net') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assignments as $assignment)
                            <tr wire:key="assignment-{{ $assignment->id }}">
                                <td>{{ $assignment->student->admission_number }} — {{ $assignment->student->fullName() }}</td>
                                <td>{{ $assignment->structure->name }} (v{{ $assignment->structure_version }})</td>
                                <td class="text-end">{{ number_format($assignment->lines->sum('net_minor') / 100, 2) }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleTrace({{ $assignment->id }})">
                                        {{ $expandedAssignmentId === $assignment->id ? __('Hide trace') : __('Why this amount?') }}
                                    </button>
                                </td>
                            </tr>
                            @if ($expandedAssignmentId === $assignment->id)
                                <tr wire:key="assignment-{{ $assignment->id }}-trace">
                                    <td colspan="4" class="bg-body-tertiary">
                                        <pre class="small mb-0 py-2" style="white-space: pre-wrap;">{{ json_encode($assignment->resolution_trace, JSON_PRETTY_PRINT) }}</pre>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No assignments computed.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($activeTab === 'exceptions')
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Admission no.') }}</th>
                            <th>{{ __('Reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($billingRun->exception_report['exceptions'] ?? []) as $exception)
                            <tr wire:key="exception-{{ $loop->index }}">
                                <td>{{ $exception['admission_number'] ?? '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::headline($exception['reason'] ?? 'unknown') }} @if (isset($exception['variance_percent'])) ({{ $exception['variance_percent'] }}%) @endif</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-body-secondary py-4">{{ __('No exceptions.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Admission no.') }}</th>
                            <th class="text-end">{{ __('Previous term') }}</th>
                            <th class="text-end">{{ __('This term') }}</th>
                            <th class="text-end">{{ __('Variance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($billingRun->variance_report ?? []) as $variance)
                            <tr wire:key="variance-{{ $loop->index }}">
                                <td>{{ $variance['admission_number'] }}</td>
                                <td class="text-end">{{ number_format($variance['previous_net_minor'] / 100, 2) }}</td>
                                <td class="text-end">{{ number_format($variance['current_net_minor'] / 100, 2) }}</td>
                                <td class="text-end {{ $variance['variance_percent'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $variance['variance_percent'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No variance data.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
