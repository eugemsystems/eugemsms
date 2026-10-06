<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Department meetings') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Minutes are a record; action items are tracked on their own so nobody has to reopen the minutes to check what is outstanding.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">{{ __('Action items') }}
                <select class="form-select form-select-sm w-auto" wire:model.live="statusFilter"><option value="">{{ __('All') }}</option><option value="open">{{ __('Open') }}</option><option value="in_progress">{{ __('In progress') }}</option><option value="done">{{ __('Done') }}</option></select></div>
                <ul class="list-group list-group-flush">
                    @forelse ($board as $row)
                        @php $owner = (int) ($row['item']['owner'] ?? 0); $status = $row['item']['status'] ?? 'open'; $mayMove = $canManage || $ownStaffId === $owner; @endphp
                        <li class="list-group-item" wire:key="ai-{{ $row['meeting']->id }}-{{ $row['index'] }}">
                            <div class="d-flex justify-content-between gap-2">
                                <div>{{ $row['item']['action'] }}<div class="small text-body-secondary">{{ $staffNames->get($owner) }} · {{ __('due') }} {{ $row['item']['due_date'] ?? '—' }} · {{ $departmentNames->get($row['meeting']->department_id) }}, {{ $row['meeting']->meeting_date->format('d M Y') }}</div></div>
                                @if ($mayMove)
                                    <select class="form-select form-select-sm w-auto align-self-start" wire:change="setStatus({{ $row['meeting']->id }}, {{ $row['index'] }}, $event.target.value)">
                                        @foreach (['open' => 'Open', 'in_progress' => 'In progress', 'done' => 'Done'] as $value => $label) <option value="{{ $value }}" @selected($status === $value)>{{ __($label) }}</option> @endforeach
                                    </select>
                                @else <span class="badge text-bg-light border align-self-start">{{ str_replace('_', ' ', $status) }}</span> @endif
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No action items.') }}</li>
                    @endforelse
                </ul>
            </div>
            <div class="card"><div class="card-header">{{ __('Recent meetings') }}</div><ul class="list-group list-group-flush">
                @forelse ($meetings as $meeting)
                    <li class="list-group-item" wire:key="mt-{{ $meeting->id }}"><strong>{{ $departmentNames->get($meeting->department_id) }}</strong> · {{ $meeting->meeting_date->format('d M Y') }} <span class="small text-body-secondary">{{ __('chaired by') }} {{ $staffNames->get($meeting->chaired_by) }}</span>
                        <div class="small" style="white-space: pre-line;">{{ $meeting->minutes }}</div></li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('No meetings yet.') }}</li>
                @endforelse
            </ul></div>
        </div>
        @if ($canManage)
            <div class="col-xl-5"><div class="card"><div class="card-header">{{ __('Record a meeting') }}</div><div class="card-body">
                <select class="form-select form-select-sm mb-2" wire:model="departmentId"><option value="">{{ __('Department…') }}</option>@foreach ($departments as $department) <option value="{{ $department->id }}">{{ $department->name }}</option> @endforeach</select>
                <input type="date" class="form-control form-control-sm mb-2" wire:model="meetingDate">
                <select class="form-select form-select-sm mb-2" wire:model="chairId"><option value="">{{ __('Chaired by…') }}</option>@foreach ($staff as $member) <option value="{{ $member->id }}">{{ $member->fullName() }}</option> @endforeach</select>
                <select class="form-select form-select-sm mb-2" multiple size="5" wire:model="attendees">@foreach ($staff as $member) <option value="{{ $member->id }}">{{ $member->fullName() }}</option> @endforeach</select>
                <textarea class="form-control form-control-sm mb-2" rows="2" wire:model="agenda" placeholder="{{ __('Agenda') }}"></textarea>
                <textarea class="form-control form-control-sm mb-2" rows="4" wire:model="minutes" placeholder="{{ __('Minutes') }}"></textarea>
                @foreach ($actionRows as $i => $row)
                    <div class="border rounded p-2 mb-2" wire:key="ar-{{ $i }}">
                        <input type="text" class="form-control form-control-sm mb-1" wire:model="actionRows.{{ $i }}.action" placeholder="{{ __('Action') }}">
                        <div class="d-flex gap-1"><select class="form-select form-select-sm" wire:model="actionRows.{{ $i }}.owner"><option value="">{{ __('Owner…') }}</option>@foreach ($staff as $member) <option value="{{ $member->id }}">{{ $member->fullName() }}</option> @endforeach</select>
                            <input type="date" class="form-control form-control-sm" wire:model="actionRows.{{ $i }}.due"><button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeActionRow({{ $i }})">&times;</button></div>
                    </div>
                @endforeach
                <button type="button" class="btn btn-link btn-sm p-0 mb-2" wire:click="addActionRow">+ {{ __('Add action item') }}</button>
                @error('minutes') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <div><button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Save minutes') }}</button></div>
            </div></div></div>
        @endif
    </div>
</div>
