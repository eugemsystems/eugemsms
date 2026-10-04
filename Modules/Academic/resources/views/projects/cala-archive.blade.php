<div>
    <h4 class="mb-1">{{ __('CALA archive') }}</h4>
    <div class="alert alert-secondary">{{ __('Read-only — the legacy Continuous Assessment Learning Activities model, preserved for archive years. Cannot be created, edited, or deleted through the application.') }}</div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Year') }}</th><th>{{ __('Learner') }}</th><th>{{ __('Subject') }}</th><th>{{ __('CALA #') }}</th><th>{{ __('Mark') }}</th><th>{{ __('%') }}</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr wire:key="cala-{{ $record->id }}">
                            <td>{{ $record->academicYear?->name }}</td>
                            <td>{{ $record->student?->first_name }} {{ $record->student?->last_name }}</td>
                            <td>{{ $record->subject?->name }}</td>
                            <td>{{ $record->cala_number }}</td>
                            <td>{{ $record->raw_mark }} / {{ $record->max_mark }}</td>
                            <td>{{ $record->percent }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No archived CALA records for this school.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
