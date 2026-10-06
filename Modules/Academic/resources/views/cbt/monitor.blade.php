<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Test monitor') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Remaining time is the server’s, not the candidate’s clock. A tab-switch count over the limit is a flag for a person to review — never a disqualification.') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <select class="form-select form-select-sm w-auto" wire:model.live="testId">@foreach ($tests as $option) <option value="{{ $option->id }}">{{ $option->title }} ({{ $option->status }})</option> @endforeach</select>
        @if ($test) <span class="badge text-bg-info">{{ $inProgress }} {{ __('in progress') }}</span> <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" wire:click="submitExpired">{{ __('Submit attempts past their time') }}</button> @endif
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Candidate') }}</th><th>{{ __('Status') }}</th><th>{{ __('Started') }}</th><th class="text-end">{{ __('Time left') }}</th><th>{{ __('Last autosave') }}</th><th class="text-end">{{ __('Extra time') }}</th><th class="text-end">{{ __('Tab switches') }}</th></tr></thead>
            <tbody>
                @forelse ($attempts as $attempt)
                    @php($live = in_array($attempt->status, ['in_progress', 'flagged']))
                    <tr wire:key="at-{{ $attempt->id }}">
                        <td>{{ $students->get($attempt->student_id)?->fullName() ?? '—' }}</td>
                        <td>{{ __(ucfirst(str_replace('_', ' ', $attempt->status))) }} @if ($attempt->auto_submitted) <span class="badge text-bg-light border">{{ __('auto-submitted') }}</span> @endif</td>
                        <td class="small">{{ $attempt->started_at?->format('H:i') }}</td>
                        <td class="text-end">{{ $live ? gmdate('H:i:s', $attempt->remainingSeconds()) : '—' }}</td>
                        <td class="small">{{ $attempt->last_autosave_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-end">{{ $attempt->extra_time_minutes ? $attempt->extra_time_minutes.' min' : '—' }}</td>
                        <td class="text-end">{{ $attempt->tab_switch_count }} @if ($limit !== null && $attempt->tab_switch_count > $limit) <span class="badge text-bg-warning">{{ __('Review') }}</span> @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No attempts yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
