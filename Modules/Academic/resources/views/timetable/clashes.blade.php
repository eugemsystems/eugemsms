<div>
    <h4 class="mb-1">{{ __('Clash inspector') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('All four hard clash levels — teacher, venue, class, and learner — for a chosen timetable.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="timetableId">
                <option value="">{{ __('Select timetable') }}</option>
                @foreach ($timetables as $timetable)
                    <option value="{{ $timetable->id }}">{{ $timetable->name }} ({{ $timetable->status }})</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($timetableId !== null)
        <div class="card">
            <div class="card-header">{{ __('Clashes') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Level') }}</th><th>{{ __('Slot A') }}</th><th>{{ __('Slot B') }}</th><th>{{ __('Affected learners') }}</th></tr></thead>
                    <tbody>
                        @forelse ($clashes as $clash)
                            <tr>
                                <td><span class="badge text-bg-danger">{{ ucfirst($clash->level) }}</span></td>
                                <td>#{{ $clash->slotIdA }} @if($slotLookup->get($clash->slotIdA)) (D{{ $slotLookup->get($clash->slotIdA)->cycle_day }}/P{{ $slotLookup->get($clash->slotIdA)->period_number }}) @endif</td>
                                <td>#{{ $clash->slotIdB }} @if($slotLookup->get($clash->slotIdB)) (D{{ $slotLookup->get($clash->slotIdB)->cycle_day }}/P{{ $slotLookup->get($clash->slotIdB)->period_number }}) @endif</td>
                                <td>
                                    @foreach ($clash->sharedLearnerIds as $studentId)
                                        <span class="badge text-bg-secondary">{{ $studentNames[$studentId] ?? "#{$studentId}" }}</span>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No clashes detected.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
