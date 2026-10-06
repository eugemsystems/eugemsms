<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Pledges') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('A pledge is a stated intention, not income. Only donations actually received count.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="mb-3"><select class="form-select form-select-sm w-auto" wire:model.live="campaignFilter"><option value="">{{ __('All campaigns') }}</option>@foreach ($campaigns as $campaign) <option value="{{ $campaign->id }}">{{ $campaign->name }}</option> @endforeach</select></div>
            <div class="card"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Donor') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Pledged') }}</th><th class="text-end">{{ __('Received') }}</th><th>{{ __('Tier') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($pledges as $pledge)
                            <tr wire:key="pg-{{ $pledge->id }}"><td>{{ $pledge->donor_name }} @if ($pledge->is_anonymous) <span class="badge text-bg-light border">{{ __('Anonymous') }}</span> @endif</td><td>{{ $pledge->donor_type }}</td><td class="text-end">{{ number_format($pledge->pledged_amount_minor / 100, 2) }} {{ $pledge->currency }}</td><td class="text-end">{{ number_format($pledge->paid_to_date_minor / 100, 2) }}</td><td>{{ $pledge->recognition_tier ?? '—' }}</td><td>{{ __(ucfirst($pledge->status)) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No pledges.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Record a pledge') }}</div><div class="card-body">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="donorName" placeholder="{{ __('Donor name') }}">
            @error('donorName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm mb-2" wire:model="donorType">@foreach (['alumnus', 'parent', 'staff', 'corporate', 'foundation'] as $t) <option value="{{ $t }}">{{ __(ucfirst($t)) }}</option> @endforeach</select>
            <div class="row g-2 mb-2"><div class="col-8"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="amount" placeholder="{{ __('Amount') }}"></div><div class="col-4"><input type="text" maxlength="3" class="form-control form-control-sm text-uppercase" wire:model="currency"></div></div>
            <select class="form-select form-select-sm mb-2" wire:model="campaignId"><option value="">{{ __('General / unrestricted') }}</option>@foreach ($campaigns->where('status', 'active') as $campaign) <option value="{{ $campaign->id }}">{{ $campaign->name }}</option> @endforeach</select>
            <select class="form-select form-select-sm mb-2" wire:model="recognitionTier"><option value="">{{ __('Recognition tier…') }}</option>@foreach (['bronze', 'silver', 'gold', 'platinum'] as $t) <option value="{{ $t }}">{{ __(ucfirst($t)) }}</option> @endforeach</select>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="an" wire:model="isAnonymous"><label class="form-check-label small" for="an">{{ __('Donor wishes to be anonymous') }}</label></div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Record') }}</button>
        </div></div></div>
    </div>
</div>
