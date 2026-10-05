<div>
    <h4 class="mb-1">{{ __('Bookable resources') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Hireable') }}</th></tr></thead>
                        <tbody>
                            @forelse ($resources as $resource)
                                <tr wire:key="resource-{{ $resource->id }}">
                                    <td>{{ $resource->code }}</td>
                                    <td>{{ $resource->name }}</td>
                                    <td>{{ $resource->resource_type }}</td>
                                    <td>{{ $resource->is_externally_hireable ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No resources.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New resource') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="resourceType">
                        @foreach (['hall', 'field', 'pool', 'laboratory', 'boardroom', 'ict_lab', 'vehicle', 'equipment'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="capacity" placeholder="{{ __('Capacity (optional)') }}">
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="requiresSetupMinutes" placeholder="{{ __('Setup minutes') }}">
                    <input type="number" class="form-control mb-2" wire:model="requiresCleaningMinutes" placeholder="{{ __('Cleaning minutes') }}">
                    <input type="number" class="form-control mb-2" wire:model="bookingLeadTimeHours" placeholder="{{ __('Booking lead time (hours)') }}">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="isExternallyHireable" wire:model.live="isExternallyHireable">
                        <label class="form-check-label" for="isExternallyHireable">{{ __('Externally hireable') }}</label>
                    </div>
                    @if ($isExternallyHireable)
                        <input type="number" class="form-control mb-2" wire:model="hireRateMinor" placeholder="{{ __('Hire rate (minor units)') }}">
                        <select class="form-select mb-2" wire:model="hireRateUnit">
                            @foreach (['hour', 'half_day', 'day', 'event'] as $unit)
                                <option value="{{ $unit }}">{{ $unit }}</option>
                            @endforeach
                        </select>
                        <input type="number" class="form-control mb-2" wire:model="depositMinor" placeholder="{{ __('Deposit (minor units)') }}">
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create resource') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
