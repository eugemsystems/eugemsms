<div>
    <h4 class="mb-1">{{ __('Timetable views') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('By class, teacher, or venue — printable via your browser\'s print dialog, or export as PDF.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <select class="form-select" wire:model.live="timetableId">
                <option value="">{{ __('Select timetable') }}</option>
                @foreach ($timetables as $timetable)
                    <option value="{{ $timetable->id }}">{{ $timetable->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" wire:model.live="mode">
                <option value="class">{{ __('By class') }}</option>
                <option value="teacher">{{ __('By teacher') }}</option>
                <option value="venue">{{ __('By venue') }}</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" wire:model.live="targetId">
                <option value="">{{ __('Select') }}</option>
                @if ($mode === 'class')
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                @elseif ($mode === 'teacher')
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                    @endforeach
                @else
                    @foreach ($venues as $venue)
                        <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                    @endforeach
                @endif
            </select>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ __('Schedule') }}</span>
            @if ($timetableId !== null && $targetId !== null)
                <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="export">{{ __('Export PDF') }}</button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Day') }}</th><th>{{ __('Period') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('Class') }}</th><th>{{ __('Venue') }}</th></tr></thead>
                <tbody>
                    @forelse ($slots as $slot)
                        <tr>
                            <td>{{ $slot->cycle_day }}</td>
                            <td>{{ $slot->period_number }}</td>
                            <td>{{ $slot->subject?->name }}</td>
                            <td>{{ $slot->staff?->first_name }} {{ $slot->staff?->last_name }}</td>
                            <td>{{ $slot->schoolClass?->name }}</td>
                            <td>{{ $slot->venue?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('Select a timetable and a target to view its schedule.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
