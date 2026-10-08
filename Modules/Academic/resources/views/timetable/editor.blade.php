<div>
    <h4 class="mb-1">{{ __('Timetable editor') }}</h4>
    <p class="text-body-secondary mb-4">{{ $timetable->name }} — {{ ucfirst($timetable->status) }}</p>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span>{{ __('Class grid — drag a lesson to move it') }}</span>
            <div class="d-flex gap-2 align-items-center">
                <select class="form-select form-select-sm w-auto" wire:model.live="gridClassId">
                    <option value="">{{ __('Choose a class…') }}</option>
                    @foreach ($classes as $class) <option value="{{ $class->id }}">{{ $class->name }}</option> @endforeach
                </select>
                @if ($lastMove)
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="undoMove">{{ __('Undo last move') }}</button>
                @endif
            </div>
        </div>
        @if ($preview !== null)
            <div class="alert {{ $preview['clear'] ? 'alert-success' : 'alert-warning' }} rounded-0 mb-0 py-2 small" role="alert">
                @if ($preview['clear'])
                    {{ __('This cell is clear — drop to move the lesson here.') }}
                @else
                    {{ __('Would clash here: :message', ['message' => $preview['message']]) }}
                @endif
            </div>
        @elseif ($clashMessage)
            <div class="alert alert-danger rounded-0 mb-0 py-2 small" role="alert">{{ $clashMessage }}</div>
        @endif
        @if ($gridClassId)
            @php($days = $periods->pluck('cycle_day')->unique()->sort()->values())
            @php($numbers = $periods->pluck('period_number')->unique()->sort()->values())
            <div class="table-responsive" x-data="{ dragging: null, hoverCell: null }">
                <table class="table table-bordered table-sm mb-0 text-center align-middle">
                    <thead><tr><th></th>@foreach ($days as $day)<th>{{ __('Day :n', ['n' => $day]) }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($numbers as $number)
                            <tr>
                                <th class="text-body-secondary small">{{ __('P:n', ['n' => $number]) }}</th>
                                @foreach ($days as $day)
                                    @php($cell = $gridSlots->get($day.'-'.$number, collect()))
                                    @if ($periods->contains(fn ($p) => $p->cycle_day === $day && $p->period_number === $number))
                                        <td style="min-width:7rem;height:3.2rem" wire:key="cell-{{ $day }}-{{ $number }}"
                                            @class(['table-success' => $preview && $preview['day'] === $day && $preview['period'] === $number && $preview['clear'], 'table-danger' => $preview && $preview['day'] === $day && $preview['period'] === $number && ! $preview['clear']])
                                            x-on:dragover.prevent="if (dragging && hoverCell !== '{{ $day }}-{{ $number }}') { hoverCell = '{{ $day }}-{{ $number }}'; $wire.previewMove(dragging, {{ $day }}, {{ $number }}) }"
                                            x-on:drop.prevent="if (dragging) { $wire.moveSlot(dragging, {{ $day }}, {{ $number }}); dragging = null; hoverCell = null }">
                                            @foreach ($cell as $tile)
                                                <div class="badge text-bg-{{ $tile->is_locked ? 'secondary' : 'primary' }} w-100 py-2" wire:key="tile-{{ $tile->id }}"
                                                     @if (! $tile->is_locked) draggable="true" x-on:dragstart="dragging = {{ $tile->id }}" x-on:dragend="dragging = null; hoverCell = null; $wire.cancelPreview()" style="cursor:grab" @endif>
                                                    {{ $tile->subject?->name }}@if ($tile->is_locked) 🔒@endif
                                                </div>
                                            @endforeach
                                        </td>
                                    @else
                                        <td class="table-light"></td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="card-body text-body-secondary small">{{ __('Choose a class to see and rearrange its week.') }}</div>
        @endif
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Slots') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Day') }}</th><th>{{ __('Period') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('Class') }}</th><th>{{ __('Venue') }}</th><th>{{ __('Locked') }}</th></tr></thead>
                        <tbody>
                            @forelse ($slots as $slot)
                                <tr wire:key="slot-{{ $slot->id }}">
                                    <td>{{ $slot->cycle_day }}</td>
                                    <td>{{ $slot->period_number }}</td>
                                    <td>{{ $slot->subject?->name }}</td>
                                    <td>{{ $slot->staff?->first_name }} {{ $slot->staff?->last_name }}</td>
                                    <td>{{ $slot->schoolClass?->name }}</td>
                                    <td>{{ $slot->venue?->name }}</td>
                                    <td>{{ $slot->is_locked ? __('Yes') : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No slots placed yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Place a lesson') }}</div>
                <div class="card-body">
                    <form wire:submit="addSlot">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="cycleDay" min="1">
                                    <label>{{ __('Cycle day') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="periodNumber" min="1">
                                    <label>{{ __('Period number') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="subjectId">
                                        <option value="">{{ __('Select subject') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Subject') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="staffId">
                                        <option value="">{{ __('Select teacher') }}</option>
                                        @foreach ($staff as $member)
                                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Teacher') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="classId">
                                        <option value="">{{ __('No whole class') }}</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Class (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="venueId">
                                        <option value="">{{ __('No venue') }}</option>
                                        @foreach ($venues as $venue)
                                            <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Venue (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12 form-check">
                                <input type="checkbox" class="form-check-input" wire:model="isLocked" id="isLocked">
                                <label class="form-check-label" for="isLocked">{{ __('Lock this slot (pin — generator will not move it)') }}</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Place lesson') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
