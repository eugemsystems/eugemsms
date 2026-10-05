<div>
    <h4 class="mb-1">{{ __('Drivers') }}</h4>

    <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="checkExpiry">{{ __('Check document expiry now') }}</button>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Staff') }}</th><th>{{ __('Licence #') }}</th><th>{{ __('Licence expires') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($drivers as $driver)
                                <tr wire:key="driver-{{ $driver->id }}" class="{{ $driver->status === 'expired_documents' ? 'table-danger' : '' }}">
                                    <td>{{ $driver->staff->fullName() }}</td>
                                    <td>{{ $driver->licence_number }}</td>
                                    <td>{{ $driver->licence_expires_on->toFormattedDateString() }}</td>
                                    <td>{{ $driver->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No drivers.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New driver') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="staffId">
                        <option value="">{{ __('Staff member') }}</option>
                        @foreach ($staffMembers as $member)
                            <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="licenceNumber" placeholder="{{ __('Licence number') }}">
                    <input type="date" class="form-control mb-2" wire:model="licenceExpiresOn" placeholder="{{ __('Licence expires on') }}">
                    <input type="date" class="form-control mb-2" wire:model="medicalExpiresOn" placeholder="{{ __('Medical certificate expires (optional)') }}">
                    <input type="date" class="form-control mb-2" wire:model="defensiveExpiresOn" placeholder="{{ __('Defensive driving cert expires (optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="yearsExperience" placeholder="{{ __('Years experience (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Register driver') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
