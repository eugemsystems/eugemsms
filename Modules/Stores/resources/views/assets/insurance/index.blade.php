<div>
    <h4 class="mb-1">{{ __('Insurance') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Under-insurance compares sum insured against the covered category\'s net book value.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Policy') }}</th><th>{{ __('Insurer') }}</th><th>{{ __('Sum insured') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($policies as $policy)
                                <tr wire:key="policy-{{ $policy->id }}">
                                    <td>{{ $policy->policy_number }}</td>
                                    <td>{{ $policy->insurer }}</td>
                                    <td>{{ number_format($policy->sum_insured_minor / 100, 2) }}</td>
                                    <td>{{ $policy->expires_on->format('Y-m-d') }}</td>
                                    <td>
                                        @if ($underInsured->contains($policy->id)) <span class="badge text-bg-danger">{{ __('under-insured') }}</span> @endif
                                        @if ($expiring->contains($policy->id)) <span class="badge text-bg-warning">{{ __('expiring') }}</span> @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No policies.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New policy') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="policyNumber" placeholder="{{ __('Policy number') }}">
                    <input type="text" class="form-control mb-2" wire:model="insurer" placeholder="{{ __('Insurer') }}">
                    <select class="form-select mb-2" wire:model="policyType">
                        <option value="all_risk">{{ __('All risk') }}</option>
                        <option value="fire">{{ __('Fire') }}</option>
                        <option value="motor">{{ __('Motor') }}</option>
                        <option value="public_liability">{{ __('Public liability') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="categoryId">
                        <option value="">{{ __('Covered category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="number" class="form-control" wire:model="sumInsuredMinor" placeholder="{{ __('Sum insured') }}"></div>
                        <div class="col-6"><input type="number" class="form-control" wire:model="premiumMinor" placeholder="{{ __('Premium') }}"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="date" class="form-control" wire:model="startsOn"></div>
                        <div class="col-6"><input type="date" class="form-control" wire:model="expiresOn"></div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
