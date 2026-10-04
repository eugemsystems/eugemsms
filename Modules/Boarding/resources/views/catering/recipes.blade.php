<div>
    <h4 class="mb-1">{{ __('Recipes') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Base servings and ingredient quantities that the daily service plan scales from.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Base servings') }}</th><th>{{ __('Allergens') }}</th></tr></thead>
                        <tbody>
                            @forelse ($recipes as $recipe)
                                <tr><td>{{ $recipe->code }}</td><td>{{ $recipe->name }}</td><td>{{ ucfirst($recipe->category) }}</td><td>{{ $recipe->base_servings }}</td><td>{{ $recipe->allergen_flags ? implode(', ', $recipe->allergen_flags) : '—' }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No recipes yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New recipe') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="category">
                        <option value="staple">{{ __('Staple') }}</option>
                        <option value="protein">{{ __('Protein') }}</option>
                        <option value="vegetable">{{ __('Vegetable') }}</option>
                        <option value="beverage">{{ __('Beverage') }}</option>
                        <option value="dessert">{{ __('Dessert') }}</option>
                        <option value="sauce">{{ __('Sauce') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="baseServings" placeholder="{{ __('Base servings') }}">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="isVegetarian" id="isVegetarian">
                        <label class="form-check-label" for="isVegetarian">{{ __('Vegetarian') }}</label>
                    </div>

                    <p class="small text-body-secondary mb-1">{{ __('Ingredients (per base servings)') }}</p>
                    @foreach ($ingredients as $i => $row)
                        <div class="row g-1 mb-1">
                            <div class="col-5"><input type="number" class="form-control form-control-sm" wire:model="ingredients.{{ $i }}.inventoryItemId" placeholder="{{ __('Item ID') }}"></div>
                            <div class="col-4"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="ingredients.{{ $i }}.quantity" placeholder="{{ __('Qty') }}"></div>
                            <div class="col-3"><input type="text" class="form-control form-control-sm" wire:model="ingredients.{{ $i }}.unit" placeholder="{{ __('Unit') }}"></div>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" wire:click="addIngredientRow">{{ __('+ ingredient') }}</button>
                    <br>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create recipe') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
