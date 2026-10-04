<div>
    <h4 class="mb-1">{{ __('Asset register') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Gapless, physically-applied tags. Capitalising an asset posts Dr Fixed Assets / Cr the contra account you choose.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <input type="text" class="form-control form-control-sm" style="max-width: 20rem" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search tag or name...') }}">
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Tag') }}</th><th>{{ __('Name') }}</th><th>{{ __('NBV') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($assets as $asset)
                                <tr wire:key="asset-{{ $asset->id }}">
                                    <td><a href="{{ route('stores.assets.register.show', ['school' => $school, 'asset' => $asset]) }}" wire:navigate>{{ $asset->asset_tag }}</a></td>
                                    <td>{{ $asset->name }}</td>
                                    <td>{{ number_format($asset->net_book_value_minor / 100, 2) }}</td>
                                    <td><span class="badge text-bg-{{ $asset->status === 'active' ? 'success' : 'secondary' }}">{{ $asset->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No assets yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Capitalise an asset') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="categoryId">
                        <option value="">{{ __('Category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="date" class="form-control" wire:model="acquisitionDate"></div>
                        <div class="col-6"><input type="number" class="form-control" wire:model="acquisitionCostMinor" placeholder="{{ __('Cost (minor)') }}"></div>
                    </div>
                    <select class="form-select mb-2" wire:model="acquisitionSource">
                        <option value="purchase">{{ __('Purchase') }}</option>
                        <option value="donation">{{ __('Donation') }}</option>
                        <option value="construction">{{ __('Construction') }}</option>
                        <option value="opening_balance">{{ __('Opening balance') }}</option>
                    </select>
                    @if ($acquisitionSource === 'donation')
                        <input type="text" class="form-control mb-2" wire:model="donorName" placeholder="{{ __('Donor name') }}">
                    @endif
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="contraAccountId">
                        <option value="">{{ __('Contra account (payable / donation income)') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="capitalize">{{ __('Capitalise') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
