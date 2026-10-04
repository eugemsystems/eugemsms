<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ $assessment->title }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Max mark') }}: {{ $assessment->max_mark }} — <span class="badge text-bg-secondary">{{ ucfirst($assessment->status) }}</span></p>
        </div>
        @if (in_array($assessment->status, ['draft', 'open'], true))
            <button type="button" class="btn btn-outline-primary" wire:click="submit">{{ __('Submit') }}</button>
        @endif
        @if (in_array($assessment->status, ['submitted', 'moderated', 'approved'], true))
            <button type="button" class="btn btn-primary" wire:click="publish" wire:confirm="{{ __('Publish? Marks will feed the aggregation pipeline.') }}">{{ __('Publish') }}</button>
        @endif
    </div>

    <div class="card">
        <div class="card-header">{{ __('Marks') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Mark') }}</th><th>{{ __('Absent') }}</th></tr></thead>
                <tbody>
                    @forelse ($roster as $student)
                        <tr wire:key="mark-{{ $student->id }}">
                            <td>{{ $student->first_name }} {{ $student->last_name }}</td>
                            <td style="width: 140px">
                                <input type="number" step="0.01" class="form-control form-control-sm" wire:model="marks.{{ $student->id }}" @disabled($absentFlags[$student->id] ?? false)>
                            </td>
                            <td style="width: 90px">
                                <input class="form-check-input" type="checkbox" wire:model="absentFlags.{{ $student->id }}">
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-4">{{ __('No enrolled learners found for this subject/term.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <button type="button" class="btn btn-primary mt-3" wire:click="saveAll">{{ __('Save marks') }}</button>
</div>
