<div>
    <h4 class="mb-1">{{ __('Menu cycles') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Cyclical planning — pick a cycle, set each day\'s recipes.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Cycles') }}</div>
                <div class="list-group list-group-flush">
                    @forelse ($cycles as $cycle)
                        <button type="button" wire:click="$set('selectedCycleId', {{ $cycle->id }})" class="list-group-item list-group-item-action {{ $selectedCycleId === $cycle->id ? 'active' : '' }}">
                            {{ $cycle->name }} ({{ $cycle->cycle_length_days }} {{ __('days') }})
                        </button>
                    @empty
                        <div class="list-group-item text-body-secondary">{{ __('No cycles yet.') }}</div>
                    @endforelse
                </div>
            </div>

            @if ($selectedCycleId)
                <div class="card">
                    <div class="card-header">{{ __('Menu days') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Day') }}</th><th>{{ __('Meal') }}</th><th>{{ __('Recipes') }}</th></tr></thead>
                            <tbody>
                                @forelse ($menuDays as $day)
                                    <tr><td>{{ $day->cycle_day }}</td><td>{{ ucfirst($day->meal) }}</td><td>{{ count($day->recipe_ids) }} {{ __('recipes') }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No days set yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('New cycle') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="cycleName" placeholder="{{ __('Name') }}">
                    <input type="number" class="form-control mb-2" wire:model="cycleLengthDays" min="1" max="28" placeholder="{{ __('Cycle length (days)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createCycle">{{ __('Create') }}</button>
                </div>
            </div>

            @if ($selectedCycleId)
                <div class="card">
                    <div class="card-header">{{ __('Set menu day') }}</div>
                    <div class="card-body">
                        <input type="number" class="form-control mb-2" wire:model="planCycleDay" min="1" placeholder="{{ __('Cycle day') }}">
                        <select class="form-select mb-2" wire:model="planMeal">
                            <option value="breakfast">{{ __('Breakfast') }}</option>
                            <option value="lunch">{{ __('Lunch') }}</option>
                            <option value="supper">{{ __('Supper') }}</option>
                            <option value="tea">{{ __('Tea') }}</option>
                            <option value="snack">{{ __('Snack') }}</option>
                        </select>
                        <select class="form-select mb-2" multiple wire:model="planRecipeIds" size="5">
                            @foreach ($recipes as $recipe)
                                <option value="{{ $recipe->id }}">{{ $recipe->name }} @if ($recipe->allergen_flags) ({{ implode(',', $recipe->allergen_flags) }}) @endif</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="setDay">{{ __('Set day') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
