<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Record a donation') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Posts Dr Bank / Cr Donation Income to the ledger (or a restricted fund account). Donations to a pledge count toward its campaign.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-6"><div class="card"><div class="card-body">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="donorName" placeholder="{{ __('Donor name') }}">
            @error('donorName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="row g-2 mb-2"><div class="col-8"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="amount" placeholder="{{ __('Amount') }}"></div><div class="col-4"><input type="text" maxlength="3" class="form-control form-control-sm text-uppercase" wire:model="currency"></div></div>
            @error('amount') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm mb-2" wire:model="bankAccountId"><option value="">{{ __('Bank account…') }}</option>@foreach ($accounts as $account) <option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option> @endforeach</select>
            @error('bankAccountId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm mb-2" wire:model="incomeAccountId"><option value="">{{ __('Income account (default: the campaign’s)') }}</option>@foreach ($accounts as $account) <option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option> @endforeach</select>
            <select class="form-select form-select-sm mb-2" wire:model="pledgeId"><option value="">{{ __('Against a pledge…') }}</option>@foreach ($pledges as $pledge) <option value="{{ $pledge->id }}">{{ $pledge->donor_name }} — {{ number_format($pledge->pledged_amount_minor / 100, 2) }} {{ $pledge->currency }}</option> @endforeach</select>
            <select class="form-select form-select-sm mb-2" wire:model="campaignId"><option value="">{{ __('Campaign (optional)…') }}</option>@foreach ($campaigns as $campaign) <option value="{{ $campaign->id }}">{{ $campaign->name }}</option> @endforeach</select>
            <select class="form-select form-select-sm mb-2" wire:model="endowmentId"><option value="">{{ __('Bursary endowment (optional)…') }}</option>@foreach ($endowments as $endowment) <option value="{{ $endowment->id }}">{{ $endowment->displayName() }}</option> @endforeach</select>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="rs" wire:model.live="isRestricted"><label class="form-check-label small" for="rs">{{ __('Restricted gift') }}</label></div>
            @if ($isRestricted) <input type="text" class="form-control form-control-sm mb-2" wire:model="restrictionPurpose" placeholder="{{ __('Purpose, e.g. endowed bursary for orphans') }}"> @endif
            <button type="button" class="btn btn-primary btn-sm" wire:click="record" wire:confirm="{{ __('Record this donation and post it to the ledger?') }}">{{ __('Record donation') }}</button>
        </div></div></div>
        <div class="col-xl-6"><div class="card"><div class="card-header">{{ __('Recent donations') }}</div><ul class="list-group list-group-flush small">
            @forelse ($recent as $donation) <li class="list-group-item d-flex justify-content-between" wire:key="rd-{{ $donation->id }}"><span>{{ $donation->donor_name }} <span class="text-body-secondary">{{ $donation->received_at?->toFormattedDateString() }}</span></span><strong>{{ number_format($donation->amount_minor / 100, 2) }} {{ $donation->currency }}</strong></li>
            @empty <li class="list-group-item text-body-secondary">{{ __('None yet.') }}</li> @endforelse
        </ul></div></div>
    </div>
</div>
