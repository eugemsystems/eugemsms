<div>
    <h4 class="mb-1">{{ __('Hostel damages') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Report, approve, dispute. A charge only ever reaches FIN-02 after approval (BR-BRD-01-012).') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Reported damages') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Hostel') }}</th><th>{{ __('Type') }}</th><th>{{ __('Liability') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($damages as $damage)
                                <tr wire:key="damage-{{ $damage->id }}">
                                    <td>{{ $damage->hostel->code }}</td>
                                    <td>{{ str_replace('_', ' ', $damage->damage_type) }}</td>
                                    <td>{{ str_replace('_', ' ', $damage->liability) }}</td>
                                    <td><span class="badge text-bg-{{ $damage->charge_status === 'disputed' ? 'danger' : ($damage->charge_status === 'charged' ? 'success' : 'secondary') }}">{{ str_replace('_', ' ', $damage->charge_status) }}</span></td>
                                    <td class="text-end">
                                        @if ($damage->charge_status === 'pending')
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="$set('approveDamageId', {{ $damage->id }})">{{ __('Approve') }}</button>
                                        @endif
                                        @if (in_array($damage->charge_status, ['pending', 'charged'], true))
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="$set('disputeDamageId', {{ $damage->id }})">{{ __('Dispute') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No damages reported.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($approveDamageId)
                <div class="card mt-3 border-success">
                    <div class="card-header">{{ __('Approve charge for damage #:id', ['id' => $approveDamageId]) }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="feeComponentId">
                            <option value="">{{ __('Fee component') }}</option>
                            @foreach ($feeComponents as $component)
                                <option value="{{ $component->id }}">{{ $component->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" class="form-control mb-2" wire:model="actualCostMinor" placeholder="{{ __('Actual cost (minor units)') }}">
                        <button type="button" class="btn btn-success btn-sm" wire:click="approve({{ $approveDamageId }})">{{ __('Approve & raise charge') }}</button>
                    </div>
                </div>
            @endif

            @if ($disputeDamageId)
                <div class="card mt-3 border-danger">
                    <div class="card-header">{{ __('Dispute damage #:id', ['id' => $disputeDamageId]) }}</div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-2" wire:model="disputeReason" placeholder="{{ __('Dispute reason') }}">
                        <button type="button" class="btn btn-danger btn-sm" wire:click="dispute({{ $disputeDamageId }})">{{ __('Record dispute') }}</button>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Report damage') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="hostelId">
                        <option value="">{{ __('Hostel') }}</option>
                        @foreach ($hostels as $hostel)
                            <option value="{{ $hostel->id }}">{{ $hostel->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="damageType">
                        <option value="window">{{ __('Window') }}</option>
                        <option value="door">{{ __('Door') }}</option>
                        <option value="furniture">{{ __('Furniture') }}</option>
                        <option value="bedding">{{ __('Bedding') }}</option>
                        <option value="plumbing">{{ __('Plumbing') }}</option>
                        <option value="electrical">{{ __('Electrical') }}</option>
                        <option value="wall">{{ __('Wall') }}</option>
                        <option value="locker">{{ __('Locker') }}</option>
                    </select>
                    <textarea class="form-control mb-2" wire:model="description" placeholder="{{ __('Description') }}"></textarea>
                    <select class="form-select mb-2" wire:model="liability">
                        <option value="individual">{{ __('Individual') }}</option>
                        <option value="shared_room">{{ __('Shared room') }}</option>
                        <option value="shared_wing">{{ __('Shared wing') }}</option>
                        <option value="school">{{ __('School') }}</option>
                        <option value="unknown">{{ __('Unknown') }}</option>
                    </select>
                    <select class="form-select mb-2" multiple wire:model="liableStudentIds" size="4">
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="estimatedCostMinor" placeholder="{{ __('Estimated cost (minor units, optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="report">{{ __('Report') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
