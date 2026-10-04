<div>
    <h4 class="mb-1">{{ __('Room inspections') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Score, findings, and a follow-up flag — feeds OPS-07\'s inter-house competition where enabled.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Recent inspections') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Room') }}</th><th>{{ __('Type') }}</th><th>{{ __('Score') }}</th><th>{{ __('Inspector') }}</th></tr></thead>
                        <tbody>
                            @forelse ($inspections as $inspection)
                                <tr>
                                    <td>{{ $inspection->inspection_date->toDateString() }}</td>
                                    <td>{{ $inspection->room->hostel->code }} / {{ $inspection->room->room_number }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $inspection->inspection_type)) }}</td>
                                    <td>{{ $inspection->total_score }} / {{ $inspection->max_score }}</td>
                                    <td>{{ $inspection->inspector->first_name }} {{ $inspection->inspector->last_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No inspections recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Record inspection') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="hostelId">
                        <option value="">{{ __('Select hostel') }}</option>
                        @foreach ($hostels as $hostel)
                            <option value="{{ $hostel->id }}">{{ $hostel->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="roomId">
                        <option value="">{{ __('Select room') }}</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->room_number }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="inspectionType">
                        <option value="routine">{{ __('Routine') }}</option>
                        <option value="spot">{{ __('Spot') }}</option>
                        <option value="end_of_term">{{ __('End of term') }}</option>
                        <option value="start_of_term">{{ __('Start of term') }}</option>
                        <option value="complaint">{{ __('Complaint') }}</option>
                    </select>
                    <div class="row g-2 mb-2">
                        <div class="col-4"><label class="form-label small mb-0">{{ __('Tidiness') }}</label><input type="number" class="form-control form-control-sm" wire:model="criteriaScores.tidiness" min="0" max="10"></div>
                        <div class="col-4"><label class="form-label small mb-0">{{ __('Cleanliness') }}</label><input type="number" class="form-control form-control-sm" wire:model="criteriaScores.cleanliness" min="0" max="10"></div>
                        <div class="col-4"><label class="form-label small mb-0">{{ __('Maintenance') }}</label><input type="number" class="form-control form-control-sm" wire:model="criteriaScores.maintenance" min="0" max="10"></div>
                    </div>
                    <input type="number" class="form-control mb-2" wire:model="maxScore" placeholder="{{ __('Max score') }}">
                    <textarea class="form-control mb-2" wire:model="findings" placeholder="{{ __('Findings (optional)') }}"></textarea>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="followUpRequired" id="followUpRequired">
                        <label class="form-check-label" for="followUpRequired">{{ __('Follow-up required') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record inspection') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
