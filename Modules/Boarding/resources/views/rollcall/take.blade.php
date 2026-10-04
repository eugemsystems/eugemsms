<div>
    <h4 class="mb-1">{{ __('Take roll call') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Pre-populated statuses show their source. A learner marked missing opens an incident immediately.') }}</p>

    @if (! $rollCall)
        <div class="card">
            <div class="card-header">{{ __('Open a roll call') }}</div>
            <div class="card-body">
                <select class="form-select mb-2" wire:model="hostelId">
                    <option value="">{{ __('Hostel') }}</option>
                    @foreach ($hostels as $hostel)
                        <option value="{{ $hostel->id }}">{{ $hostel->name }}</option>
                    @endforeach
                </select>
                <select class="form-select mb-2" wire:model="rollCallPointId">
                    <option value="">{{ __('Roll call point') }}</option>
                    @foreach ($points as $point)
                        <option value="{{ $point->id }}">{{ $point->name }} ({{ $point->scheduled_time }})</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-primary btn-sm" wire:click="open">{{ __('Open roll call') }}</button>
            </div>
        </div>
    @else
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ __('Expected') }}: {{ $rollCall->expected_count }} · {{ __('Present') }}: {{ $rollCall->present_count }} · {{ __('Accounted') }}: {{ $rollCall->accounted_count }} · <strong class="text-danger">{{ __('Missing') }}: {{ $rollCall->missing_count }}</strong></span>
                <span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="markAllPresentRemaining">{{ __('Mark all remaining present') }}</button>
                    <button type="button" class="btn btn-sm btn-success" wire:click="complete">{{ __('Complete roll call') }}</button>
                </span>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Status') }}</th><th>{{ __('Source') }}</th><th>{{ __('Override note') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($roster as $allocation)
                            @php $record = $recordsByStudentId->get($allocation->student_id); @endphp
                            <tr wire:key="roster-{{ $allocation->student_id }}">
                                <td>{{ $allocation->student->first_name }} {{ $allocation->student->last_name }}</td>
                                <td>
                                    @if ($record)
                                        <span class="badge text-bg-{{ $record->status === 'missing' ? 'danger' : ($record->status === 'present' ? 'success' : 'secondary') }}">{{ str_replace('_', ' ', $record->status) }}</span>
                                    @else
                                        <span class="text-body-secondary">{{ __('unmarked') }}</span>
                                    @endif
                                </td>
                                <td class="small text-body-secondary">
                                    @if ($record?->is_auto_populated)
                                        {{ __('auto') }}: {{ $record->source_reference }}
                                    @endif
                                </td>
                                <td>
                                    @if ($record?->is_auto_populated)
                                        <input type="text" class="form-control form-control-sm" wire:model="overrideNotes.{{ $allocation->student_id }}" placeholder="{{ __('Required to override') }}">
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="mark({{ $allocation->student_id }}, 'present')">{{ __('Present') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="mark({{ $allocation->student_id }}, 'missing')">{{ __('Missing') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="mark({{ $allocation->student_id }}, 'late')">{{ __('Late') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
