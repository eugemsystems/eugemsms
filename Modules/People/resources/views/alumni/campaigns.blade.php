<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Campaigns') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Progress is the money actually received — never the pledged total.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-8">
            @forelse ($campaigns as $campaign)
                @php($pct = $campaign->target_amount_minor > 0 ? min(100, round($campaign->raised_amount_minor / $campaign->target_amount_minor * 100)) : 0)
                <div class="card mb-3" wire:key="cp-{{ $campaign->id }}"><div class="card-body">
                    <div class="d-flex justify-content-between"><strong>{{ $campaign->name }}</strong><span class="badge text-bg-{{ $campaign->status === 'active' ? 'success' : 'secondary' }}">{{ __(ucfirst($campaign->status)) }}</span></div>
                    <div class="small text-body-secondary mb-2">{{ $campaign->purpose }}</div>
                    <div class="progress mb-1" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $pct }}%">{{ $pct }}%</div></div>
                    <div class="small d-flex justify-content-between"><span>{{ __('Received') }}: <strong>{{ number_format($campaign->raised_amount_minor / 100, 2) }}</strong></span><span>{{ __('Target') }}: {{ number_format($campaign->target_amount_minor / 100, 2) }} {{ $campaign->currency }}</span><span class="text-body-secondary">{{ __('Pledged') }}: {{ number_format(($campaign->pledged_total ?? 0) / 100, 2) }}</span></div>
                </div></div>
            @empty
                <div class="text-body-secondary">{{ __('No campaigns yet.') }}</div>
            @endforelse
        </div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('New campaign') }}</div><div class="card-body">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="name" placeholder="{{ __('Name') }}">
            @error('name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <textarea class="form-control form-control-sm mb-2" rows="3" wire:model="purpose" placeholder="{{ __('Purpose') }}"></textarea>
            <div class="row g-2 mb-2"><div class="col-8"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="target" placeholder="{{ __('Target') }}"></div><div class="col-4"><input type="text" maxlength="3" class="form-control form-control-sm text-uppercase" wire:model="currency"></div></div>
            <div class="row g-2 mb-2"><div class="col-6"><input type="date" class="form-control form-control-sm" wire:model="startsOn"></div><div class="col-6"><input type="date" class="form-control form-control-sm" wire:model="endsOn"></div></div>
            <select class="form-select form-select-sm mb-2" wire:model="incomeAccountId"><option value="">{{ __('Donation income account…') }}</option>@foreach ($accounts as $account) <option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option> @endforeach</select>
            @error('incomeAccountId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
        </div></div></div>
    </div>
</div>
