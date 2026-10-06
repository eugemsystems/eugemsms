<div>
    <h4 class="mb-3">{{ __('Attendance reports') }}</h4>

    <ul class="nav nav-tabs mb-3">
        @foreach (['class' => __('Class report'), 'heatmap' => __('Learner heatmap'), 'followup' => __('Absence follow-up')] as $key => $label)
            <li class="nav-item"><button type="button" class="nav-link {{ $tab === $key ? 'active' : '' }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button></li>
        @endforeach
    </ul>

    @if ($tab !== 'followup')
        <div class="row g-2 mb-3 align-items-end" style="max-width:56rem">
            <div class="col-md-3"><label class="form-label small mb-0">{{ __('From') }}</label><input type="date" class="form-control form-control-sm" wire:model.live="from"></div>
            <div class="col-md-3"><label class="form-label small mb-0">{{ __('To') }}</label><input type="date" class="form-control form-control-sm" wire:model.live="to"></div>
            @if ($tab === 'class')
                <div class="col-md-3">
                    <label class="form-label small mb-0">{{ __('Class') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="classId">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($classes as $class) <option value="{{ $class->id }}">{{ $class->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-3"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="export">{{ __('Download register (CSV)') }}</button></div>
            @endif
        </div>
        @error('classId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
        @error('to') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
    @endif

    @if ($tab === 'class')
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Learner') }}</th><th class="text-end">{{ __('Present') }}</th><th class="text-end">{{ __('Late') }}</th><th class="text-end">{{ __('Absent') }}</th><th class="text-end">{{ __('Excused') }}</th><th class="text-end">{{ __('Attendance') }}</th></tr></thead>
            <tbody>
                @forelse ($classReport as $row)
                    <tr wire:key="cr-{{ $row['student']->id }}"><td>{{ $row['student']->last_name }}, {{ $row['student']->first_name }} <span class="text-body-secondary small">{{ $row['student']->admission_number }}</span></td>
                        <td class="text-end">{{ $row['present'] }}</td><td class="text-end">{{ $row['late'] }}</td><td class="text-end">{{ $row['absent'] }}</td><td class="text-end">{{ $row['excused'] }}</td>
                        <td class="text-end">@if ($row['percent'] !== null)<span class="badge text-bg-{{ $row['percent'] < 80 ? 'danger' : ($row['percent'] < 90 ? 'warning' : 'success') }}">{{ $row['percent'] }}%</span>@else — @endif</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ $classId === null ? __('Choose a class.') : __('No registers marked in this range.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
        <p class="small text-body-secondary mt-2">{{ __('Attendance counts present and late against present, late and absent; excused days are left out. Unmarked days are never counted as present.') }}</p>
    @elseif ($tab === 'heatmap')
        <input type="text" class="form-control form-control-sm mb-2" style="max-width:20rem" wire:model.live.debounce.300ms="learnerSearch" placeholder="{{ __('Search a learner') }}">
        <div class="mb-3">@foreach ($candidates as $candidate)<button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1" wire:click="$set('studentId', {{ $candidate->id }})" wire:key="cand-{{ $candidate->id }}">{{ $candidate->last_name }}, {{ $candidate->first_name }}</button>@endforeach</div>
        @if ($heatmap)
            <h6>{{ $heatmap['student']->first_name }} {{ $heatmap['student']->last_name }}</h6>
            <table class="table table-sm table-bordered text-center" style="max-width:32rem">
                <thead><tr>@foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as $d)<th>{{ __($d) }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($heatmap['weeks'] as $week)
                        <tr>@foreach ($week as $cell)
                            @php($colour = ['present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'excused' => 'info'][$cell['status'] ?? ''] ?? 'light')
                            <td class="table-{{ $colour }} small" @if ($cell) title="{{ $cell['date'] }}: {{ $cell['status'] ?? __('not marked') }}" @endif>{{ $cell ? substr($cell['date'], 8) : '' }}</td>
                        @endforeach</tr>
                    @endforeach
                </tbody>
            </table>
            <p class="small text-body-secondary">{{ __('Green present, amber late, red absent, blue excused, grey not marked.') }}</p>
        @endif
    @else
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Learner') }}</th><th>{{ __('Parent told') }}</th></tr></thead>
            <tbody>
                @forelse ($followUp as $record)
                    <tr wire:key="fu-{{ $record->id }}"><td>{{ $record->session_date->format('Y-m-d') }}</td><td>{{ $record->student?->last_name }}, {{ $record->student?->first_name }}</td>
                        <td>@if ($record->guardian_notified_at)<span class="badge text-bg-success">{{ $record->guardian_notified_at->format('d M H:i') }}</span>@else<span class="badge text-bg-secondary">{{ __('not yet') }}</span>@endif</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No absences in the last 14 days.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    @endif
</div>
