<div>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('Coverage tracker') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Planned week against the week a topic was actually taught. A fact for the HOD to act on, not a verdict.') }}</p>
        </div>
        <select class="form-select form-select-sm w-auto" wire:model.live="termId">@foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach</select>
    </div>
    @forelse ($schemes as $scheme)
        @php
            $topics = $coverage[$scheme->id] ?? [];
            $behind = collect($topics)->where('status', 'behind')->count();
            $mayRecord = in_array($scheme->id, $recordable, true);
        @endphp
        <div class="card mb-3" wire:key="s-{{ $scheme->id }}">
            <div class="card-header d-flex justify-content-between">
                <span><strong>{{ $teacherNames->get($scheme->teacher_staff_id) }}</strong> — {{ $subjectNames->get($scheme->subject_id) }} · {{ $gradeNames->get($scheme->grade_level_id) }} <span class="badge text-bg-light border">{{ $scheme->status }}</span></span>
                <span class="badge {{ $behind > 0 ? 'text-bg-warning' : 'text-bg-success' }}">{{ $behind > 0 ? $behind.' '.__('behind') : __('on track') }}</span>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Topic') }}</th><th class="text-end">{{ __('Planned wk') }}</th><th class="text-end">{{ __('Taught wk') }}</th><th>{{ __('Status') }}</th><th>{{ __('Note') }}</th>@if ($mayRecord)<th></th>@endif</tr></thead>
                <tbody>
                    @foreach ($topics as $row)
                        <tr wire:key="t-{{ $scheme->id }}-{{ $row['index'] }}">
                            <td>{{ $row['topic'] }}</td><td class="text-end">{{ $row['plannedWeek'] }}</td><td class="text-end">{{ $row['deliveredWeek'] ?? '—' }}</td>
                            <td><span class="badge {{ ['on_track' => 'text-bg-success', 'behind' => 'text-bg-warning', 'not_yet_due' => 'text-bg-light border'][$row['status']] }}">{{ str_replace('_', ' ', $row['status']) }}</span></td>
                            <td class="small">{{ $row['varianceNote'] }}</td>
                            @if ($mayRecord)
                                <td class="text-end text-nowrap">
                                    <input type="date" class="form-control form-control-sm d-inline-block w-auto" wire:model="dates.{{ $scheme->id }}:{{ $row['index'] }}">
                                    <input type="text" class="form-control form-control-sm d-inline-block w-auto" wire:model="notes.{{ $scheme->id }}:{{ $row['index'] }}" placeholder="{{ __('Note') }}" maxlength="255">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="record({{ $scheme->id }}, {{ $row['index'] }})">{{ __('Save') }}</button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </div>
    @empty
        <div class="text-body-secondary">{{ __('No schemes of work in scope for this term.') }}</div>
    @endforelse
</div>
