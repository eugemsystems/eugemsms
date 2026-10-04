<div>
    <h4 class="mb-1">{{ __('Seating plan') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Auto-allocate with spacing; separate-room arrangements seated alone first. Also serves as the printable attendance sheet (special arrangements shown).') }}</p>

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="paperId">
                <option value="">{{ __('Select paper') }}</option>
                @foreach ($papers as $paper)
                    <option value="{{ $paper->id }}">{{ $paper->subject?->name }} — {{ $paper->paper_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">{{ __('Venues (in seating order)') }}</label>
            <select class="form-select" wire:model="venueIds" multiple>
                @foreach ($venues as $venue)
                    <option value="{{ $venue->id }}">{{ $venue->name }} ({{ $venue->exam_capacity ?? $venue->capacity }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-primary w-100" wire:click="allocate">{{ __('Auto-allocate') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Venue') }}</th><th>{{ __('Seat') }}</th><th>{{ __('Candidate') }}</th><th>{{ __('Special arrangement') }}</th></tr></thead>
                <tbody>
                    @forelse ($seatings as $seating)
                        @php $arrangement = $arrangements->get($seating->candidate?->student_id); @endphp
                        <tr wire:key="seating-{{ $seating->id }}">
                            <td>{{ $seating->venue?->name }}</td>
                            <td>{{ $seating->seat_number }}</td>
                            <td>{{ $seating->candidate?->student?->first_name }} {{ $seating->candidate?->student?->last_name }} ({{ $seating->candidate?->index_number }})</td>
                            <td>
                                @if ($arrangement)
                                    <span class="badge text-bg-info">{{ ucfirst(str_replace('_', ' ', $arrangement->arrangement_type)) }}</span>
                                    @if ($arrangement->extra_time_percent)
                                        <span class="text-body-secondary">+{{ $arrangement->extra_time_percent }}%</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No seating allocated yet for this paper.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
