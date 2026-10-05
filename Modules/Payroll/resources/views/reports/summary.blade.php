<div>
    <h4 class="mb-3">{{ __('Payroll reports') }}</h4>

    @if ($current)
        <div class="row g-2 mb-3 small">
            <div class="col-auto"><div class="card p-2">{{ __('Headcount') }}: <strong>{{ $current->staff_count }}</strong></div></div>
            <div class="col-auto"><div class="card p-2">{{ __('Gross') }}: <strong>{{ number_format($current->gross_minor / 100, 2) }}</strong></div></div>
            <div class="col-auto"><div class="card p-2">{{ __('PAYE') }}: <strong>{{ number_format($current->paye_minor / 100, 2) }}</strong></div></div>
            <div class="col-auto"><div class="card p-2">{{ __('NSSA (both)') }}: <strong>{{ number_format(($current->nssa_employee_minor + $current->nssa_employer_minor) / 100, 2) }}</strong></div></div>
            <div class="col-auto"><div class="card p-2">{{ __('Employer cost') }}: <strong>{{ number_format($current->employer_cost_minor / 100, 2) }}</strong></div></div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Staff') }}</th><th>{{ __('Gross') }}</th><th>{{ __('Net') }}</th><th>{{ __('PAYE') }}</th><th>{{ __('Employer cost') }}</th></tr></thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr wire:key="summary-{{ $run->id }}">
                            <td>{{ $run->period_month }}</td>
                            <td>{{ $run->staff_count }}</td>
                            <td>{{ number_format($run->gross_minor / 100, 2) }}</td>
                            <td>{{ number_format($run->net_minor / 100, 2) }}</td>
                            <td>{{ number_format($run->paye_minor / 100, 2) }}</td>
                            <td>{{ number_format($run->employer_cost_minor / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No posted payroll runs yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
