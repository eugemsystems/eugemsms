<div>
    <h4 class="mb-1">{{ __('Compute results') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Runs the full aggregation and position-recomputation pipeline for every learner in a class.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="classId">
                <option value="">{{ __('Select class') }}</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-primary w-100" wire:click="compute">{{ __('Compute') }}</button>
        </div>
    </div>

    @if ($weightExceptions !== [])
        <div class="alert alert-warning">
            <strong>{{ __('Results blocked — assessment weights must total 100% for:') }}</strong>
            <ul class="mb-0 mt-1">
                @foreach ($weightExceptions as $exception)
                    <li>{{ $exception }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($classId !== null)
        <div class="card">
            <div class="card-header">{{ __('Term results') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Position') }}</th><th>{{ __('Student') }}</th><th>{{ __('Average %') }}</th><th>{{ __('Subjects passed') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($results as $result)
                            <tr wire:key="result-{{ $result->id }}">
                                <td>{{ $result->class_position ?? '—' }}</td>
                                <td>{{ $result->student?->first_name }} {{ $result->student?->last_name }}</td>
                                <td>{{ $result->average_percent ?? '—' }}</td>
                                <td>{{ $result->subjects_passed }} / {{ $result->subjects_taken }}</td>
                                <td><span class="badge text-bg-secondary">{{ ucfirst($result->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No results computed for this class yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
