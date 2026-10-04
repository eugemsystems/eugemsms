<div>
    <h4 class="mb-1">{{ __('Mark entry') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('When double marking is enabled, the first marker\'s value is never shown to the second marker.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="paperId">
                <option value="">{{ __('Select paper') }}</option>
                @foreach ($papers as $paper)
                    <option value="{{ $paper->id }}">{{ $paper->subject?->name }} — {{ $paper->paper_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Candidate') }}</th><th>{{ __('Status') }}</th><th>{{ __('Mark') }}</th><th>{{ __('Absent') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($candidates as $candidate)
                        @php $existing = $existingMarks->get($candidate->id); @endphp
                        <tr wire:key="candidate-{{ $candidate->id }}">
                            <td>{{ $candidate->student?->first_name }} {{ $candidate->student?->last_name }} ({{ $candidate->index_number }})</td>
                            <td><span class="badge text-bg-secondary">{{ $existing?->status ?? 'pending' }}</span></td>
                            <td style="width: 120px"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="marks.{{ $candidate->id }}"></td>
                            <td><input type="checkbox" class="form-check-input" wire:model="absentFlags.{{ $candidate->id }}"></td>
                            <td><button type="button" class="btn btn-sm btn-primary" wire:click="save({{ $candidate->id }})">{{ __('Save') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No confirmed candidates entered for this paper\'s subject.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
