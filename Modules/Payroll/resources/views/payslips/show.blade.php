<div>
    <h4 class="mb-1">{{ __('Payslip') }} {{ $payslip->payslip_number }}</h4>

    @if (! $this->canViewCompensation())
        <div class="alert alert-secondary">{{ __('Salary, banking and calculation detail are restricted to holders of staff.view_compensation.') }}</div>
    @else
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">{{ __('Earnings & deductions') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><td>{{ __('Basic') }}</td><td class="text-end">{{ number_format($payslip->basic_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('Allowances') }}</td><td class="text-end">{{ number_format($payslip->allowances_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('Overtime') }}</td><td class="text-end">{{ number_format($payslip->overtime_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('Bonus') }}</td><td class="text-end">{{ number_format($payslip->bonus_minor / 100, 2) }}</td></tr>
                                <tr class="table-light"><td><strong>{{ __('Gross') }}</strong></td><td class="text-end"><strong>{{ number_format($payslip->gross_minor / 100, 2) }}</strong></td></tr>
                                <tr><td>{{ __('PAYE') }}</td><td class="text-end">-{{ number_format($payslip->paye_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('AIDS Levy') }}</td><td class="text-end">-{{ number_format($payslip->aids_levy_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('NSSA (employee)') }}</td><td class="text-end">-{{ number_format($payslip->nssa_employee_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('NEC (employee)') }}</td><td class="text-end">-{{ number_format($payslip->nec_employee_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('Loan deduction') }}</td><td class="text-end">-{{ number_format($payslip->loan_deduction_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('Fee offset') }}</td><td class="text-end">-{{ number_format($payslip->fee_offset_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('Third party') }}</td><td class="text-end">-{{ number_format($payslip->third_party_minor / 100, 2) }}</td></tr>
                                <tr class="table-light"><td><strong>{{ __('Net pay') }}</strong></td><td class="text-end"><strong>{{ number_format($payslip->net_pay_minor / 100, 2) }} {{ $payslip->currency }}</strong></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header">{{ __('Employer cost') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><td>{{ __('NSSA (employer)') }}</td><td class="text-end">{{ number_format($payslip->nssa_employer_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('APWCS') }}</td><td class="text-end">{{ number_format($payslip->apwcs_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('ZIMDEF') }}</td><td class="text-end">{{ number_format($payslip->zimdef_minor / 100, 2) }}</td></tr>
                                <tr><td>{{ __('NEC (employer)') }}</td><td class="text-end">{{ number_format($payslip->nec_employer_minor / 100, 2) }}</td></tr>
                                <tr class="table-light"><td><strong>{{ __('Total employer cost') }}</strong></td><td class="text-end"><strong>{{ number_format($payslip->employer_cost_minor / 100, 2) }}</strong></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">{{ __('Calculation trace') }}</div>
                    <div class="card-body">
                        @forelse ((array) $payslip->calculation_trace as $step)
                            <div class="mb-2 pb-2 border-bottom small">
                                <strong>{{ $step['type'] ?? 'note' }}</strong>
                                <pre class="mb-0 small text-wrap">{{ json_encode($step, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @empty
                            <p class="text-body-secondary">{{ __('No trace recorded.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
