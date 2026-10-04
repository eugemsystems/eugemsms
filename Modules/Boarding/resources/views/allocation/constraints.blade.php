<div>
    <h4 class="mb-1">{{ __('Allocation constraints') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Gender segregation is enforced unconditionally in code and is never listed here as something to configure.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Active constraints') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Weight') }}</th><th>{{ __('Hostel') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                        <tbody>
                            @forelse ($constraints as $constraint)
                                <tr>
                                    <td>{{ str_replace('_', ' ', $constraint->constraint_type) }}</td>
                                    <td><span class="badge text-bg-{{ $constraint->severity === 'hard' ? 'danger' : 'secondary' }}">{{ $constraint->severity }}</span></td>
                                    <td>{{ $constraint->weight }}</td>
                                    <td>{{ $constraint->hostel?->code ?? __('All') }}</td>
                                    <td>{{ $constraint->reason }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No constraints configured.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New constraint') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <select class="form-select mb-2" wire:model="constraintType">
                            <option value="level_band">{{ __('Level band') }}</option>
                            <option value="house_affinity">{{ __('House affinity') }}</option>
                            <option value="sibling_together">{{ __('Sibling together') }}</option>
                            <option value="sibling_apart">{{ __('Sibling apart') }}</option>
                            <option value="medical_proximity">{{ __('Medical proximity') }}</option>
                            <option value="mobility_ground_floor">{{ __('Mobility ground floor') }}</option>
                            <option value="max_level_spread">{{ __('Max level spread') }}</option>
                            <option value="prefect_room_only">{{ __('Prefect room only') }}</option>
                            <option value="learner_incompatibility">{{ __('Learner incompatibility') }}</option>
                        </select>
                        <select class="form-select mb-2" wire:model="severity">
                            <option value="soft">{{ __('Soft (weighted)') }}</option>
                            <option value="hard">{{ __('Hard (never violated)') }}</option>
                        </select>
                        <input type="number" class="form-control mb-2" wire:model="weight" min="1" max="10" placeholder="{{ __('Weight') }}">
                        <select class="form-select mb-2" wire:model="hostelId">
                            <option value="">{{ __('All hostels') }}</option>
                            @foreach ($hostels as $hostel)
                                <option value="{{ $hostel->id }}">{{ $hostel->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" class="form-control mb-2" wire:model="value" placeholder="{{ __('Numeric value (optional)') }}">
                        <input type="text" class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason (optional)') }}">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Create') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
