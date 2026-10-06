<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Course spaces') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('One per teaching group per term, mirrored from the timetable — there is no separate class list to keep.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Group') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($spaces as $space)
                        <tr wire:key="cs-{{ $space->id }}"><td>{{ $groupNames[$space->teaching_group_id] ?? '—' }}</td><td>{{ $subjectNames[$space->subject_id] ?? '—' }}</td><td>{{ $space->is_active ? __('Active') : __('Inactive') }}</td><td class="text-end"><a href="{{ route('academic.lms.space', [$school, $space->id]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Open') }}</a></td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No course spaces yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Create from a teaching group') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="teachingGroupId"><option value="">{{ __('Teaching group…') }}</option>@foreach ($groups as $group) <option value="{{ $group->id }}">{{ $group->name }} ({{ $group->code }})</option> @endforeach</select>
            @error('teachingGroupId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
        </div></div></div>
    </div>
</div>
