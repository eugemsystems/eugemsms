<div>
    <h4 class="mb-3">{{ __('Management reports') }}</h4>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button type="button" class="nav-link {{ $tab === 'departmental' ? 'active' : '' }}" wire:click="$set('tab', 'departmental')">{{ __('Departmental') }}</button></li>
        <li class="nav-item"><button type="button" class="nav-link {{ $tab === 'collection' ? 'active' : '' }}" wire:click="$set('tab', 'collection')">{{ __('Fee collection') }}</button></li>
    </ul>
    <div class="row g-2 mb-3" style="max-width:40rem">
        <div class="col-4"><label class="form-label small mb-0">{{ __('From') }}</label><input type="date" class="form-control form-control-sm" wire:model.live="periodStart"></div>
        <div class="col-4"><label class="form-label small mb-0">{{ __('To') }}</label><input type="date" class="form-control form-control-sm" wire:model.live="periodEnd"></div>
        <div class="col-4"><label class="form-label small mb-0">{{ __('Currency') }}</label><input type="text" maxlength="3" class="form-control form-control-sm" wire:model.live="currency"></div>
    </div>
    @php($fmt = fn (int $minor): string => number_format($minor / 100, 2))

    @if ($tab === 'departmental')
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Cost centre') }}</th><th class="text-end">{{ __('Income') }}</th><th class="text-end">{{ __('Expense') }}</th><th class="text-end">{{ __('Net') }}</th></tr></thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr wire:key="d-{{ $row['cost_centre_id'] ?? 'none' }}"><td>{{ $row['code'] }} — {{ $row['name'] }}</td><td class="text-end">{{ $fmt($row['income_minor']) }}</td><td class="text-end">{{ $fmt($row['expense_minor']) }}</td><td class="text-end">{{ $fmt($row['net_minor']) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Nothing posted in this period.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr class="table-light"><td><strong>{{ __('Total') }}</strong></td><td class="text-end"><strong>{{ $fmt($report['total_income_minor']) }}</strong></td><td class="text-end"><strong>{{ $fmt($report['total_expense_minor']) }}</strong></td><td class="text-end"><strong>{{ $fmt($report['total_net_minor']) }}</strong></td></tr></tfoot>
        </table></div></div>
    @else
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Grade level') }}</th><th class="text-end">{{ __('Learners') }}</th><th class="text-end">{{ __('Billed') }}</th><th class="text-end">{{ __('Collected') }}</th><th class="text-end">{{ __('Outstanding') }}</th><th class="text-end">{{ __('Rate') }}</th></tr></thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr wire:key="c-{{ $loop->index }}"><td>{{ $row['grade_level'] }}</td><td class="text-end">{{ $row['learners'] }}</td><td class="text-end">{{ $fmt($row['billed_minor']) }}</td><td class="text-end">{{ $fmt($row['paid_minor']) }}</td><td class="text-end">{{ $fmt($row['outstanding_minor']) }}</td><td class="text-end">{{ $row['rate_percent'] !== null ? $row['rate_percent'].'%' : '—' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No invoices issued in this period.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr class="table-light"><td colspan="2"><strong>{{ __('Total') }}</strong></td><td class="text-end"><strong>{{ $fmt($report['total_billed_minor']) }}</strong></td><td class="text-end"><strong>{{ $fmt($report['total_paid_minor']) }}</strong></td><td class="text-end"><strong>{{ $fmt($report['total_outstanding_minor']) }}</strong></td><td class="text-end"><strong>{{ $report['rate_percent'] !== null ? $report['rate_percent'].'%' : '—' }}</strong></td></tr></tfoot>
        </table></div></div>
    @endif
</div>
