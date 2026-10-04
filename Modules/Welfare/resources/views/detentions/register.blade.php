<div>
    <h4 class="mb-1">{{ __('Detention register') }}</h4>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Date') }}</th><th>{{ __('Time') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($detentions as $detention)
                                <tr wire:key="detention-{{ $detention->id }}">
                                    <td>{{ $detention->student?->first_name }} {{ $detention->student?->last_name }}</td>
                                    <td>{{ $detention->scheduled_date->toDateString() }}</td>
                                    <td>{{ $detention->starts_at }}–{{ $detention->ends_at }}</td>
                                    <td><span class="badge text-bg-{{ $detention->status === 'missed' ? 'danger' : ($detention->status === 'attended' ? 'success' : 'secondary') }}">{{ $detention->status }}</span></td>
                                    <td>
                                        @if ($detention->status === 'scheduled')
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="markAttendance({{ $detention->id }}, true)">{{ __('Attended') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="markAttendance({{ $detention->id }}, false)">{{ __('Missed') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No detentions scheduled.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Schedule detention') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="scheduledDate">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="time" class="form-control" wire:model="startsAt"></div>
                        <div class="col-6"><input type="time" class="form-control" wire:model="endsAt"></div>
                    </div>
                    <input type="text" class="form-control mb-2" wire:model="venue" placeholder="{{ __('Venue (optional)') }}">
                    <textarea class="form-control mb-2" wire:model="taskSet" placeholder="{{ __('Task set (optional)') }}"></textarea>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="schedule">{{ __('Schedule') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
