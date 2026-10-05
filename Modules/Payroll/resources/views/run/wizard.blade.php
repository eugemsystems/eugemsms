<div>
    <h4 class="mb-1">{{ __('Payroll run') }} ⭐</h4>
    <p class="text-body-secondary small">{{ __('Compute → preview → approve → post. No run posts without a separate approval of the preview.') }}</p>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">{{ __('Compute a new run') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="runType">
                        <option value="regular">{{ __('Regular') }}</option>
                        <option value="supplementary">{{ __('Supplementary') }}</option>
                        <option value="bonus">{{ __('Bonus') }}</option>
                        <option value="terminal">{{ __('Terminal') }}</option>
                    </select>
                    <label class="form-label small mb-0">{{ __('Pay date') }}</label>
                    <input type="date" class="form-control mb-2" wire:model="payDate">
                    <label class="form-label small mb-0">{{ __('Period start') }}</label>
                    <input type="date" class="form-control mb-2" wire:model="periodStart">
                    <label class="form-label small mb-0">{{ __('Period end') }}</label>
                    <input type="date" class="form-control mb-2" wire:model="periodEnd">
                    <button type="button" class="btn btn-primary btn-sm w-100" wire:click="compute">{{ __('Compute run') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Run #') }}</th><th>{{ __('Period') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($runs as $run)
                                <tr wire:key="run-{{ $run->id }}" class="{{ $selectedRun?->id === $run->id ? 'table-active' : '' }}">
                                    <td>{{ $run->run_number }}</td>
                                    <td>{{ $run->period_month }}</td>
                                    <td><span class="badge bg-secondary">{{ $run->status }}</span></td>
                                    <td><button type="button" class="btn btn-outline-secondary btn-sm" wire:click="select({{ $run->id }})">{{ __('Open') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No payroll runs yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @if ($selectedRun)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ __('Run') }} {{ $selectedRun->run_number }} — {{ __('status') }}: <span class="badge bg-secondary">{{ $selectedRun->status }}</span></span>
                        <div>
                            @if ($selectedRun->status === 'preview')
                                <button type="button" class="btn btn-success btn-sm" wire:click="approve">{{ __('Approve') }}</button>
                            @endif
                            @if ($selectedRun->status === 'approved')
                                <select class="form-select form-select-sm d-inline-block mb-0" style="width:auto" wire:model="feeDebtorsAccountId">
                                    <option value="">{{ __('Fee debtors account') }}</option>
                                    @foreach ($feeDebtorsAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-primary btn-sm" wire:click="post">{{ __('Post') }}</button>
                            @endif
                            @if ($selectedRun->status === 'posted')
                                <button type="button" class="btn btn-outline-primary btn-sm" wire:click="distribute">{{ __('Distribute payslips') }}</button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 small">
                            <div class="col">{{ __('Staff') }}: <strong>{{ $selectedRun->staff_count }}</strong></div>
                            <div class="col">{{ __('Gross') }}: <strong>{{ number_format($selectedRun->gross_minor / 100, 2) }}</strong></div>
                            <div class="col">{{ __('Net') }}: <strong>{{ number_format($selectedRun->net_minor / 100, 2) }}</strong></div>
                            <div class="col">{{ __('PAYE') }}: <strong>{{ number_format($selectedRun->paye_minor / 100, 2) }}</strong></div>
                            <div class="col">{{ __('Employer cost') }}: <strong>{{ number_format($selectedRun->employer_cost_minor / 100, 2) }}</strong></div>
                        </div>

                        @if (! empty($selectedRun->exception_report))
                            <div class="alert alert-warning mt-3 mb-0">
                                <strong>{{ __('Exceptions') }} ({{ count($selectedRun->exception_report) }})</strong>
                                <ul class="mb-0 small">
                                    @foreach ($selectedRun->exception_report as $exception)
                                        <li>{{ __('Staff') }} #{{ $exception['staff_id'] }} — {{ $exception['message'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (! empty($selectedRun->variance_report))
                            <div class="alert alert-info mt-3 mb-0">
                                <strong>{{ __('Variance beyond threshold') }} ({{ count($selectedRun->variance_report) }})</strong>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">{{ __('Payslips') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Staff') }}</th><th>{{ __('Gross') }}</th><th>{{ __('PAYE') }}</th><th>{{ __('NSSA') }}</th><th>{{ __('Net') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($payslips as $payslip)
                                    <tr wire:key="payslip-{{ $payslip->id }}">
                                        <td>{{ $payslip->staff?->first_name }} {{ $payslip->staff?->last_name }}</td>
                                        <td>{{ number_format($payslip->gross_minor / 100, 2) }}</td>
                                        <td>{{ number_format($payslip->paye_minor / 100, 2) }}</td>
                                        <td>{{ number_format($payslip->nssa_employee_minor / 100, 2) }}</td>
                                        <td>{{ number_format($payslip->net_pay_minor / 100, 2) }}</td>
                                        <td><a href="{{ route('payroll.payslips.show', [$school, $payslip]) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('View') }}</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No payslips computed.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="card"><div class="card-body text-body-secondary">{{ __('Select or compute a run to see its preview.') }}</div></div>
            @endif
        </div>
    </div>
</div>
