<div>
    <h4 class="mb-1">{{ __('Occupancy board') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Occupied and free beds per hostel. Gender segregation has no override.') }}</p>

    <div class="d-flex gap-2 mb-4 flex-wrap">
        @foreach ($hostels as $h)
            <button type="button" wire:click="$set('hostelId', {{ $h->id }})" class="btn btn-sm {{ $hostel && $hostel->id === $h->id ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $h->code }} ({{ $h->capacity }})
            </button>
        @endforeach
    </div>

    @if ($hostel)
        <div class="row g-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ $hostel->name }} — {{ __('Free') }}: {{ max(0, $hostel->capacity - $activeAllocationsByBedId->count()) }} / {{ $hostel->capacity }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Room') }}</th><th>{{ __('Bed') }}</th><th>{{ __('Occupant') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($rooms as $room)
                                    @foreach ($room->beds as $bed)
                                        @php $allocation = $activeAllocationsByBedId->get($bed->id); @endphp
                                        <tr wire:key="bed-{{ $bed->id }}">
                                            <td>{{ $room->room_number }}</td>
                                            <td>{{ $bed->bed_number }} @unless ($bed->is_available) <span class="badge text-bg-secondary">{{ __('unavailable') }}</span> @endunless</td>
                                            <td>
                                                @if ($allocation)
                                                    {{ $allocation->student->first_name }} {{ $allocation->student->last_name }}
                                                @else
                                                    <span class="text-body-secondary">{{ __('Free') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @if ($allocation)
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('moveAllocationId', {{ $allocation->id }})">{{ __('Move') }}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="let r = prompt('{{ __('Reason for ending this allocation:') }}'); if (r) { @this.call('endAllocation', {{ $allocation->id }}, r) }">{{ __('End') }}</button>
                                                @elseif ($moveAllocationId)
                                                    <button type="button" class="btn btn-sm btn-success" wire:click="move({{ $bed->id }})">{{ __('Move here') }}</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No rooms in this hostel yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                @if ($moveAllocationId)
                    <div class="card mb-3 border-primary">
                        <div class="card-header">{{ __('Moving learner — pick a free bed above') }}</div>
                        <div class="card-body">
                            <input type="text" class="form-control mb-2" wire:model="moveReason" placeholder="{{ __('Reason for move') }}">
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('moveAllocationId', null)">{{ __('Cancel') }}</button>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header">{{ __('Allocate a learner to') }} {{ $hostel->name }}</div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-2" wire:model.live.debounce.400ms="allocateStudentSearch" placeholder="{{ __('Search by name or admission number') }}">
                        @if ($searchResults->isNotEmpty())
                            <div class="list-group mb-2">
                                @foreach ($searchResults as $student)
                                    <button type="button" class="list-group-item list-group-item-action {{ $allocateStudentId === $student->id ? 'active' : '' }}" wire:click="$set('allocateStudentId', {{ $student->id }})">
                                        {{ $student->first_name }} {{ $student->last_name }} ({{ $student->admission_number }}) — {{ ucfirst($student->gender) }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        <button type="button" class="btn btn-primary btn-sm" wire:click="allocate">{{ __('Allocate') }}</button>
                        <p class="text-body-secondary small mt-2 mb-0">{{ __('Refused outright if the learner\'s gender does not match this hostel — no override exists.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card"><div class="card-body text-body-secondary">{{ __('No hostels exist yet — create one in Hostel structure.') }}</div></div>
    @endif
</div>
