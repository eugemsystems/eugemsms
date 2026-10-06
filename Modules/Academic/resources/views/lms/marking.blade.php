<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('academic.lms.space', [$school, $assignment->course_space_id]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate><i class="ri ri-arrow-left-line"></i></a>
        <div><h4 class="mb-0">{{ $assignment->title }}</h4><p class="text-body-secondary small mb-0">{{ __('A similarity flag asks you to look — nothing is penalised automatically. Final mark = raw mark less any late penalty, fixed when you mark.') }}</p></div>
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Attempt') }}</th><th>{{ __('Submitted') }}</th><th>{{ __('Flags') }}</th><th class="text-end">{{ __('Raw') }}</th><th class="text-end">{{ __('Penalty') }}</th><th class="text-end">{{ __('Final') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($submissions as $submission)
                    <tr wire:key="sb-{{ $submission->id }}">
                        <td>{{ $students->get($submission->student_id)?->fullName() ?? '—' }}</td><td>{{ $submission->attempt_number }}</td>
                        <td class="small">{{ $submission->submitted_at?->toDayDateTimeString() }}</td>
                        <td>@if ($submission->is_late) <span class="badge text-bg-warning">{{ __('Late') }} {{ $submission->minutes_late }}m</span> @endif @if ($submission->similarity_flag) <span class="badge text-bg-danger">{{ __('Similar to :n other(s)', ['n' => count($submission->similarity_matches ?? [])]) }}</span> @endif</td>
                        <td class="text-end">{{ $submission->raw_mark ?? '—' }}</td><td class="text-end">{{ $submission->penalty_applied_percent !== null ? $submission->penalty_applied_percent.'%' : '—' }}</td><td class="text-end">{{ $submission->final_mark ?? '—' }}</td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="begin({{ $submission->id }})">{{ $submission->status === 'marked' ? __('Re-mark') : __('Mark') }}</button></td>
                    </tr>
                    @if ($submission->submitted_text || $submission->submitted_link || $markingId === $submission->id)
                        <tr wire:key="sb-d-{{ $submission->id }}"><td colspan="8" class="small">
                            @if ($submission->submitted_text) <div class="border rounded p-2 mb-2" style="white-space: pre-line">{{ $submission->submitted_text }}</div> @endif
                            @if ($submission->submitted_link) <div class="mb-2"><a href="{{ $submission->submitted_link }}" rel="noopener noreferrer nofollow" target="_blank" class="text-break">{{ $submission->submitted_link }}</a></div> @endif
                            @if ($markingId === $submission->id)
                                <div class="row g-2 align-items-start">
                                    <div class="col-md-2"><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="rawMark" placeholder="{{ __('Raw mark') }}{{ $assignment->max_mark ? ' / '.$assignment->max_mark : '' }}">@error('rawMark') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-8"><textarea class="form-control form-control-sm" rows="2" wire:model="feedback" placeholder="{{ __('Feedback') }}"></textarea></div>
                                    <div class="col-md-2"><button type="button" class="btn btn-primary btn-sm" wire:click="mark">{{ __('Save') }}</button></div>
                                </div>
                            @endif
                        </td></tr>
                    @endif
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-3">{{ __('No submissions yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
