<div>
    <h4 class="mb-1">{{ __('Livestock register') }}</h4>

    <div class="row g-2 mb-3 align-items-end" style="max-width:30rem">
        <div class="col-8">
            <select class="form-select" wire:model="mortalityUnitId">
                <option value="">{{ __('Check mortality for unit...') }}</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-4"><button type="button" class="btn btn-outline-primary btn-sm w-100" wire:click="checkMortality">{{ __('Check (90d)') }}</button></div>
    </div>
    @if ($mortalityResult !== null)
        <div class="alert alert-info py-2" style="max-width:30rem">{{ __('Mortality rate:') }} {{ number_format($mortalityResult, 1) }}%</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Tag') }}</th><th>{{ __('Species') }}</th><th>{{ __('Unit') }}</th><th>{{ __('Head') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($livestock as $animal)
                                <tr wire:key="livestock-{{ $animal->id }}">
                                    <td>{{ $animal->tag_number ?? '—' }}</td>
                                    <td>{{ $animal->species }}</td>
                                    <td>{{ $animal->productionUnit->name }}</td>
                                    <td>{{ $animal->head_count }}</td>
                                    <td>{{ $animal->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No livestock.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New livestock') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="productionUnitId">
                        <option value="">{{ __('Production unit') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="species">
                        @foreach (['cattle', 'goat', 'sheep', 'pig', 'poultry'] as $sp)
                            <option value="{{ $sp }}">{{ $sp }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model.live="purpose">
                        @foreach (['breeding', 'dairy', 'meat', 'layers', 'broilers', 'draught'] as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="isHerdRecord" wire:model="isHerdRecord">
                        <label class="form-check-label" for="isHerdRecord">{{ __('Herd-level record (e.g. poultry flock)') }}</label>
                    </div>
                    <input type="number" class="form-control mb-2" wire:model="headCount" placeholder="{{ __('Head count') }}">
                    <input type="text" class="form-control mb-2" wire:model="tagNumber" placeholder="{{ __('Tag number (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="breed" placeholder="{{ __('Breed (optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="acquisitionCostMinor" placeholder="{{ __('Acquisition cost (minor units, optional)') }}">

                    @if ($purpose === 'breeding')
                        <p class="small text-body-secondary">{{ __('Breeding stock above threshold capitalises as a FIN-10 fixed asset.') }}</p>
                        <select class="form-select mb-2" wire:model="capitalizeCategoryId">
                            <option value="">{{ __('Asset category (optional)') }}</option>
                            @foreach ($assetCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="capitalizeCostCentreId">
                            <option value="">{{ __('Cost centre (optional)') }}</option>
                            @foreach ($costCentres as $cc)
                                <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="capitalizeContraAccountId">
                            <option value="">{{ __('Contra account (optional)') }}</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create livestock record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
