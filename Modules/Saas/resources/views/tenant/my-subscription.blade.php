<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('My subscription') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Your plan, usage and invoices. An upgrade takes effect now with a prorated invoice; a downgrade takes effect at your next renewal.') }}</p>
    </div>

    @if (! $subscription)
        <div class="alert alert-info">{{ __('No subscription is on file for your organisation yet.') }}</div>
    @else
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="card h-100"><div class="card-body">
                    <div class="small text-body-secondary">{{ __('Current plan') }}</div>
                    <div class="fs-4">{{ $subscription->plan->name }}</div>
                    <span class="badge text-bg-{{ in_array($subscription->status, ['active', 'trial']) ? 'success' : 'warning' }}">{{ __(ucfirst(str_replace('_', ' ', $subscription->status))) }}</span>
                    <div class="small mt-2">{{ __('Period') }}: {{ $subscription->current_period_start->toFormattedDateString() }} – {{ $subscription->current_period_end->toFormattedDateString() }}</div>
                    @if ($pendingChange)
                        <div class="alert alert-light border small mt-3 mb-0">{{ __('A downgrade is scheduled for :date.', ['date' => $pendingChange->effective_from->toFormattedDateString()]) }}</div>
                    @endif
                </div></div>
            </div>
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">{{ __('Usage this month') }}</div>
                    <ul class="list-group list-group-flush small">
                        @forelse ($usage as $meter)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ str_replace('_', ' ', $meter->metric) }}</span>
                                <span>{{ rtrim(rtrim(number_format($meter->usage_value, 2), '0'), '.') }}@if ($meter->limit_value !== null) / {{ rtrim(rtrim(number_format($meter->limit_value, 2), '0'), '.') }} @endif
                                    @if ($meter->hard_limit_reached) <span class="badge text-bg-danger">{{ __('Limit reached') }}</span> @elseif ($meter->soft_warning_sent) <span class="badge text-bg-warning">{{ __('Nearing limit') }}</span> @endif</span>
                            </li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('No usage recorded yet this month.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">{{ __('Plans') }}</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Plan') }}</th><th class="text-end">{{ __('Monthly at your size') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            <tr wire:key="p-{{ $plan['id'] }}">
                                <td>{{ $plan['name'] }} @if ($plan['isCurrent']) <span class="badge text-bg-primary">{{ __('Current') }}</span> @endif</td>
                                <td class="text-end">{{ number_format($plan['monthly'] / 100, 2) }} {{ $plan['currency'] }}</td>
                                <td class="text-end">
                                    @unless ($plan['isCurrent'])
                                        <button type="button" class="btn btn-sm btn-outline-{{ $plan['direction'] === 'upgrade' ? 'primary' : 'secondary' }}" wire:click="changePlan({{ $plan['id'] }})" wire:confirm="{{ $plan['direction'] === 'upgrade' ? __('Upgrade now? A prorated invoice is issued immediately.') : __('Downgrade at your next renewal?') }}">{{ $plan['direction'] === 'upgrade' ? __('Upgrade') : __('Downgrade') }}</button>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">{{ __('Invoices') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Period') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr wire:key="i-{{ $invoice->id }}"><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->period_month }}</td><td>{{ $invoice->due_date?->toFormattedDateString() }}</td><td class="text-end">{{ number_format($invoice->total_minor / 100, 2) }} {{ $invoice->currency }}</td><td>{{ __(ucfirst($invoice->status)) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No invoices yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
