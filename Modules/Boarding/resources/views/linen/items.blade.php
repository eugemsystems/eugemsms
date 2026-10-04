<div>
    <h4 class="mb-1">{{ __('Issuable items catalogue') }}</h4>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Returnable') }}</th><th>{{ __('Replacement cost') }}</th></tr></thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr><td>{{ $item->code }}</td><td>{{ $item->name }}</td><td>{{ ucfirst($item->category) }}</td><td>{{ $item->is_returnable ? __('Yes') : __('No') }}</td><td>{{ $item->replacement_cost_minor ?? '—' }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No items yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New item') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="category">
                        <option value="bedding">{{ __('Bedding') }}</option>
                        <option value="linen">{{ __('Linen') }}</option>
                        <option value="uniform">{{ __('Uniform') }}</option>
                        <option value="equipment">{{ __('Equipment') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="replacementCostMinor" placeholder="{{ __('Replacement cost (minor units)') }}">
                    <input type="number" class="form-control mb-2" wire:model="expectedLifespanTerms" placeholder="{{ __('Expected lifespan (terms)') }}">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="isReturnable" id="isReturnable">
                        <label class="form-check-label" for="isReturnable">{{ __('Returnable') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="requiresTagging" id="requiresTagging">
                        <label class="form-check-label" for="requiresTagging">{{ __('Requires tagging') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Add') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
