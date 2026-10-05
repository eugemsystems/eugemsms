<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Plan catalogue') }}</h4><p class="text-body-secondary small mb-0">{{ __('Withdrawing a plan stops new sales and plan changes to it; existing subscribers keep it.') }}</p></div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Tier') }}</th><th>{{ __('Price') }}</th><th>{{ __('Learner band') }}</th><th class="text-end">{{ __('Subscribers') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($plans as $plan)
                    <tr wire:key="pl-{{ $plan->id }}">
                        <td>{{ $plan->code }}</td><td>{{ $plan->name }}</td><td>{{ $plan->tier }}</td>
                        <td>{{ $plan->flat_monthly_minor !== null ? number_format($plan->flat_monthly_minor / 100, 2).' '.__('flat/month') : number_format(($plan->price_per_learner_minor ?? 0) / 100, 2).' '.__('per learner') }} {{ $plan->currency }}</td>
                        <td>{{ $plan->learner_band_min ?? 0 }}–{{ $plan->learner_band_max ?? '∞' }}</td>
                        <td class="text-end">{{ $subscriberCounts[$plan->id] ?? 0 }}</td>
                        <td>@if ($plan->is_active)<span class="badge text-bg-success">{{ __('Offered') }}</span>@else<span class="badge text-bg-secondary">{{ __('Withdrawn') }}</span>@endif</td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="setActive({{ $plan->id }}, {{ $plan->is_active ? 'false' : 'true' }})">{{ $plan->is_active ? __('Withdraw') : __('Offer') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div></div>
</div>
