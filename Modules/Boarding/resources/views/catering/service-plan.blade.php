<div>
    <h4 class="mb-1">{{ __('Daily service plan') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Servings scale to who is actually present — never the nominal roll.') }}</p>

    <div class="card mb-4">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3"><input type="date" class="form-control" wire:model="serviceDate"></div>
            <div class="col-md-2">
                <select class="form-select" wire:model="meal">
                    <option value="breakfast">{{ __('Breakfast') }}</option>
                    <option value="lunch">{{ __('Lunch') }}</option>
                    <option value="supper">{{ __('Supper') }}</option>
                    <option value="tea">{{ __('Tea') }}</option>
                    <option value="snack">{{ __('Snack') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" wire:model="menuDayId">
                    <option value="">{{ __('No menu day') }}</option>
                    @foreach ($menuDays as $day)
                        <option value="{{ $day->id }}">{{ __('Day') }} {{ $day->cycle_day }} — {{ ucfirst($day->meal) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1"><input type="number" class="form-control" wire:model="staffMeals" placeholder="{{ __('Staff') }}"></div>
            <div class="col-md-1"><input type="number" class="form-control" wire:model="guestMeals" placeholder="{{ __('Guests') }}"></div>
            <div class="col-md-2"><button type="button" class="btn btn-primary w-100" wire:click="plan">{{ __('Plan') }}</button></div>
        </div>
    </div>

    @if ($service)
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Occupancy & servings') }}</div>
                    <div class="card-body">
                        <p class="mb-1">{{ __('Nominal (allocated)') }}: <strong>{{ $service->nominal_boarders }}</strong></p>
                        <p class="mb-1">{{ __('Present (used for servings)') }}: <strong class="text-success">{{ $service->present_boarders }}</strong></p>
                        <p class="mb-1">{{ __('On exeat') }}: {{ $service->on_exeat }} · {{ __('Sick bay') }}: {{ $service->in_sick_bay }}</p>
                        <p class="mb-1">{{ __('Planned servings') }}: <strong>{{ $service->planned_servings }}</strong></p>
                        <p class="mb-0">{{ __('Cost per serving') }}: {{ $service->cost_per_serving_minor !== null ? $service->cost_per_serving_minor : __('unavailable') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">{{ __('Requisition (required quantities)') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Required') }}</th><th>{{ __('Unit') }}</th></tr></thead>
                            <tbody>
                                @forelse ($service->requisitionLines as $line)
                                    <tr><td>#{{ $line->inventory_item_id }}</td><td>{{ $line->required_quantity }}</td><td>{{ $line->unit }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No recipe lines (no menu day picked).') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($service->status !== 'closed')
                    <div class="card">
                        <div class="card-header">{{ __('Close service') }}</div>
                        <div class="card-body">
                            <input type="number" class="form-control mb-2" wire:model="actualServed" placeholder="{{ __('Actual served (required)') }}">
                            <input type="text" class="form-control mb-2" wire:model="wastageNote" placeholder="{{ __('Wastage note (optional)') }}">
                            <button type="button" class="btn btn-success btn-sm" wire:click="close({{ $service->id }})">{{ __('Close') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="card"><div class="card-body text-body-secondary">{{ __('No service planned for this date/meal yet.') }}</div></div>
    @endif
</div>
