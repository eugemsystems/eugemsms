<div>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('Review results') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Check each class, write the comments, then approve. Only approved results can have report cards generated.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" wire:model.live="classId"><option value="">{{ __('Class…') }}</option>@foreach ($classes as $class) <option value="{{ $class->id }}">{{ $class->name }}</option> @endforeach</select>
            @if ($classId) <button type="button" class="btn btn-primary btn-sm" wire:click="approve" wire:confirm="{{ __('Approve every computed result in this class?') }}">{{ __('Approve class') }}</button> @endif
        </div>
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th class="text-end">#</th><th>{{ __('Learner') }}</th><th class="text-end">{{ __('Average') }}</th><th class="text-end">{{ __('Passed') }}</th><th class="text-end">{{ __('Attendance') }}</th><th>{{ __('Promotion') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($results as $result)
                <tr wire:key="r-{{ $result->id }}">
                    <td class="text-end">{{ $result->class_position ?? '—' }}</td><td>{{ $result->student?->fullName() }}</td>
                    <td class="text-end">{{ $result->average_percent ?? '—' }}</td><td class="text-end">{{ $result->subjects_passed }}/{{ $result->subjects_taken }}</td>
                    <td class="text-end">{{ $result->attendance_percent ?? '—' }}</td><td class="small">{{ str_replace('_', ' ', (string) $result->promotion_recommendation) }}</td>
                    <td><span class="badge text-bg-light border">{{ $result->status }}</span></td>
                    <td class="text-end">@if ($canComment && $result->status !== 'published') <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="edit({{ $result->id }})">{{ __('Comments') }}</button> @endif</td>
                </tr>
                @if ($editingId === $result->id)
                    <tr wire:key="e-{{ $result->id }}"><td colspan="8">
                        <textarea class="form-control form-control-sm mb-2" rows="2" wire:model="classTeacherComment" placeholder="{{ __('Class teacher comment') }}"></textarea>
                        <textarea class="form-control form-control-sm mb-2" rows="2" wire:model="headComment" placeholder="{{ __('Head comment') }}"></textarea>
                        @error('classTeacherComment') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                        @if ($subjectResults->isNotEmpty())
                            <p class="small text-body-secondary mb-1">{{ __('Per-subject comments') }}</p>
                            @foreach ($subjectResults as $subjectResult)
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text" style="width: 160px">{{ $subjectResult->subject_name }}</span>
                                    <input type="text" class="form-control" maxlength="500" wire:model="subjectComments.{{ $subjectResult->id }}" placeholder="{{ __('Subject comment') }}">
                                </div>
                            @endforeach
                        @endif
                        <button type="button" class="btn btn-sm btn-primary" wire:click="saveComments">{{ __('Save') }}</button>
                    </td></tr>
                @endif
            @empty
                <tr><td colspan="8" class="text-center text-body-secondary py-3">{{ __('Choose a class to see its results.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div></div>
</div>
