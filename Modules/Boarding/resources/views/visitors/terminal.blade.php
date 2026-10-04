<div>
    <h4 class="mb-1">{{ __('Visitor terminal') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A blacklisted visitor is refused outright and the attempt is logged as a security event.') }}</p>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Sign in') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="fullName" placeholder="{{ __('Full name') }}">
                    <select class="form-select mb-2" wire:model="visitPurpose">
                        <option value="parent_visit">{{ __('Parent visit') }}</option>
                        <option value="meeting">{{ __('Meeting') }}</option>
                        <option value="delivery">{{ __('Delivery') }}</option>
                        <option value="contractor">{{ __('Contractor') }}</option>
                        <option value="inspection">{{ __('Inspection') }}</option>
                        <option value="prospective_parent">{{ __('Prospective parent') }}</option>
                        <option value="medical">{{ __('Medical') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="idType" placeholder="{{ __('ID type (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="idNumber" placeholder="{{ __('ID number (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="phone" placeholder="{{ __('Phone (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="vehicleRegistration" placeholder="{{ __('Vehicle registration (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="signIn">{{ __('Sign in') }}</button>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('On site now') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Visitor') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Signed in') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($onSite as $entry)
                                <tr wire:key="visit-{{ $entry->id }}">
                                    <td>{{ $entry->visitor->full_name }} {{ $entry->visitor->is_watchlisted ? '⚠' : '' }}</td>
                                    <td>{{ str_replace('_', ' ', $entry->visit_purpose) }}</td>
                                    <td>{{ $entry->signed_in_at->format('H:i') }}</td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="signOut({{ $entry->id }})">{{ __('Sign out') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Nobody on site.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
