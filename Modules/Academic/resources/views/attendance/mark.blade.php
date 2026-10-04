<div>
    <h4 class="mb-1">{{ __('Mark register') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Daily mode. An unmarked register is never treated as present.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="classId">
                <option value="">{{ __('Select class') }}</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" class="form-control" wire:model.live="sessionDate">
        </div>
        <div class="col-md-2">
            @if ($session !== null)
                <span class="badge text-bg-{{ $session->status === 'completed' ? 'success' : ($session->status === 'partial' ? 'warning' : 'secondary') }} py-2">{{ ucfirst($session->status) }}</span>
            @endif
        </div>
    </div>

    @if ($classId !== null)
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                {{ __('Roster') }}
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="markAllPresent">{{ __('Mark all present') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Existing') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($roster as $student)
                            <tr wire:key="roster-{{ $student->id }}">
                                <td>{{ $student->first_name }} {{ $student->last_name }}</td>
                                <td>
                                    <select class="form-select form-select-sm" wire:model="statuses.{{ $student->id }}">
                                        <option value="">{{ __('— not marked —') }}</option>
                                        <option value="present">{{ __('Present') }}</option>
                                        <option value="absent">{{ __('Absent') }}</option>
                                        <option value="late">{{ __('Late') }}</option>
                                        <option value="excused">{{ __('Excused') }}</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" wire:model="reasonCodeIds.{{ $student->id }}">
                                        <option value="">{{ __('—') }}</option>
                                        @foreach ($reasonCodes as $reasonCode)
                                            <option value="{{ $reasonCode->id }}">{{ $reasonCode->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    @if ($existingRecords->has($student->id))
                                        <span class="badge text-bg-secondary">{{ ucfirst($existingRecords[$student->id]->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($existingRecords->has($student->id))
                                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="amend({{ $student->id }})">{{ __('Amend') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No active allocations for this class on this date.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-2 mt-3">
            <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" wire:model="amendmentReason" placeholder="{{ __('Amendment reason (needed for Amend button above)') }}">
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input" type="checkbox" wire:model="overrideLock" id="override-lock">
                    <label class="form-check-label small" for="override-lock">{{ __('Override lock (requires permission)') }}</label>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-primary mt-3" wire:click="save">{{ __('Save register') }}</button>
    @endif
</div>
