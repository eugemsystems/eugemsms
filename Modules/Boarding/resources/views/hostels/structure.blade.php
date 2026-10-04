<div>
    <h4 class="mb-1">{{ __('Hostel structure') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }} — {{ __('hostel, wing, room and bed hierarchy.') }}</p>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">{{ __('Hostels') }}</div>
                <div class="list-group list-group-flush">
                    @forelse ($hostels as $hostel)
                        <button type="button" wire:click="selectHostel({{ $hostel->id }})" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selectedHostelId === $hostel->id ? 'active' : '' }}">
                            <span>{{ $hostel->code }} — {{ $hostel->name }}</span>
                            <span class="badge text-bg-{{ $hostel->gender === 'male' ? 'primary' : 'danger' }}">{{ ucfirst($hostel->gender) }}</span>
                        </button>
                    @empty
                        <div class="list-group-item text-body-secondary">{{ __('No hostels yet.') }}</div>
                    @endforelse
                </div>
            </div>

                <div class="card">
                    <div class="card-header">{{ __('New hostel') }}</div>
                    <div class="card-body">
                        <form wire:submit="createHostel">
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" class="form-control @error('hostelCode') is-invalid @enderror" wire:model="hostelCode" placeholder="{{ __('Code') }}">
                                </div>
                                <div class="col-6">
                                    <select class="form-select" wire:model="hostelGender">
                                        <option value="male">{{ __('Male') }}</option>
                                        <option value="female">{{ __('Female') }}</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <input type="text" class="form-control @error('hostelName') is-invalid @enderror" wire:model="hostelName" placeholder="{{ __('Name') }}">
                                </div>
                                <div class="col-6">
                                    <select class="form-select" wire:model="housemasterStaffId">
                                        <option value="">{{ __('Housemaster') }}</option>
                                        @foreach ($staffOptions as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->first_name }} {{ $staff->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-select" wire:model="matronStaffId">
                                        <option value="">{{ __('Matron') }}</option>
                                        @foreach ($staffOptions as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->first_name }} {{ $staff->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm mt-3">{{ __('Create hostel') }}</button>
                        </form>
                    </div>
                </div>
        </div>

        <div class="col-md-8">
            @if ($selectedHostel === null)
                <div class="card"><div class="card-body text-body-secondary">{{ __('Select a hostel to manage its wings, rooms and beds.') }}</div></div>
            @else
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ $selectedHostel->code }} — {{ $selectedHostel->name }}</span>
                        <span>
                            <span class="badge text-bg-info me-2">{{ __('Capacity') }}: {{ $selectedHostel->capacity }}</span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="recalculateCapacity({{ $selectedHostel->id }})">{{ __('Recalculate') }}</button>
                                        </span>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>{{ __('Housemaster') }}:</strong> {{ $selectedHostel->housemaster ? $selectedHostel->housemaster->first_name.' '.$selectedHostel->housemaster->last_name : '—' }}</p>
                        <p class="mb-0"><strong>{{ __('Matron') }}:</strong> {{ $selectedHostel->matron ? $selectedHostel->matron->first_name.' '.$selectedHostel->matron->last_name : '—' }}</p>
                    </div>
                </div>

                <div class="row g-4">
                                <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">{{ __('Add wing') }}</div>
                                <div class="card-body">
                                    <form wire:submit="createWing" class="row g-2">
                                        <div class="col-5"><input type="text" class="form-control" wire:model="wingCode" placeholder="{{ __('Code') }}"></div>
                                        <div class="col-7"><input type="text" class="form-control" wire:model="wingName" placeholder="{{ __('Name') }}"></div>
                                        <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Add wing') }}</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">{{ __('Add room') }}</div>
                                <div class="card-body">
                                    <form wire:submit="createRoom" class="row g-2">
                                        <div class="col-6"><input type="text" class="form-control" wire:model="roomNumber" placeholder="{{ __('Room number') }}"></div>
                                        <div class="col-6">
                                            <select class="form-select" wire:model="roomType">
                                                <option value="dormitory">{{ __('Dormitory') }}</option>
                                                <option value="cubicle">{{ __('Cubicle') }}</option>
                                                <option value="single">{{ __('Single') }}</option>
                                                <option value="prefect">{{ __('Prefect') }}</option>
                                                <option value="isolation">{{ __('Isolation') }}</option>
                                                <option value="staff">{{ __('Staff') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <select class="form-select" wire:model="wingId">
                                                <option value="">{{ __('No wing') }}</option>
                                                @foreach ($selectedHostel->wings as $wing)
                                                    <option value="{{ $wing->id }}">{{ $wing->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6"><input type="number" min="1" class="form-control" wire:model="bedCount" placeholder="{{ __('Bed count') }}"></div>
                                        <div class="col-6">
                                            <select class="form-select" wire:model="proximityToExit">
                                                <option value="">{{ __('Exit proximity (optional)') }}</option>
                                                <option value="near">{{ __('Near') }}</option>
                                                <option value="mid">{{ __('Mid') }}</option>
                                                <option value="far">{{ __('Far') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-6 d-flex align-items-center">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" wire:model="isGroundFloor" id="isGroundFloor">
                                                <label class="form-check-label" for="isGroundFloor">{{ __('Ground floor') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Add room') }}</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        </div>

                <div class="card mt-4">
                    <div class="card-header">{{ __('Rooms & beds') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Room') }}</th><th>{{ __('Type') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Beds') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($selectedHostel->rooms as $room)
                                    <tr wire:key="room-{{ $room->id }}">
                                        <td>{{ $room->room_number }} @if ($room->wing) <span class="text-body-secondary">({{ $room->wing->name }})</span> @endif</td>
                                        <td>{{ ucfirst($room->room_type) }}</td>
                                        <td><span class="badge text-bg-{{ $room->condition_grade === 'out_of_service' ? 'danger' : 'success' }}">{{ str_replace('_', ' ', $room->condition_grade) }}</span></td>
                                        <td>
                                            @foreach ($room->beds as $bed)
                                                <span class="badge text-bg-{{ $bed->is_available ? 'light text-dark' : 'secondary' }} me-1">{{ $bed->bed_number }}</span>
                                            @endforeach
                                        </td>
                                        <td class="text-end">
                                                                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('bedRoomId', {{ $room->id }})">{{ __('Add bed') }}</button>
                                                @if ($room->condition_grade !== 'out_of_service')
                                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="$set('outOfServiceRoomId', {{ $room->id }})">{{ __('Out of service') }}</button>
                                                @endif
                                                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No rooms yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($bedRoomId !== null)
                    <div class="card mt-3">
                        <div class="card-header">{{ __('Add bed to room') }}</div>
                        <div class="card-body">
                            <form wire:submit="createBed" class="row g-2">
                                <div class="col-5"><input type="text" class="form-control" wire:model="bedNumber" placeholder="{{ __('Bed number') }}"></div>
                                <div class="col-5">
                                    <select class="form-select" wire:model="bedType">
                                        <option value="single">{{ __('Single') }}</option>
                                        <option value="bunk_upper">{{ __('Bunk (upper)') }}</option>
                                        <option value="bunk_lower">{{ __('Bunk (lower)') }}</option>
                                    </select>
                                </div>
                                <div class="col-2"><button type="submit" class="btn btn-primary">{{ __('Add') }}</button></div>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($outOfServiceRoomId !== null)
                    <div class="card mt-3 border-danger">
                        <div class="card-header">{{ __('Mark room out of service') }}</div>
                        <div class="card-body">
                            <p class="text-body-secondary">{{ __('Refused while the room is occupied (BR-BRD-01-009) — reallocate occupants first.') }}</p>
                            <div class="input-group">
                                <input type="text" class="form-control" wire:model="outOfServiceReason" placeholder="{{ __('Reason') }}">
                                <button type="button" class="btn btn-danger" wire:click="markOutOfService({{ $outOfServiceRoomId }})">{{ __('Confirm') }}</button>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
