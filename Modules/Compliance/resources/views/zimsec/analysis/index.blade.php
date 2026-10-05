<div>
    <h4 class="mb-1">{{ __('ZIMSEC pass-rate analysis') }} 🇿🇼</h4>

    <select class="form-select mb-3" style="max-width:420px" wire:model="registrationId" wire:change="analyse">
        <option value="0">{{ __('Select registration') }}</option>
        @foreach ($registrations as $registration)
            <option value="{{ $registration->id }}">{{ $registration->exam_level }} — {{ $registration->exam_series }}</option>
        @endforeach
    </select>

    @if ($result)
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ __('By subject') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Candidates') }}</th><th>{{ __('Pass rate') }}</th></tr></thead>
                            <tbody>
                                @forelse ($result['bySubject'] as $row)
                                    <tr><td>{{ $row['subject_name'] }}</td><td>{{ $row['candidates'] }}</td><td>{{ $row['pass_rate'] }}%</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No results yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ __('By class') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Class') }}</th><th>{{ __('Candidates') }}</th><th>{{ __('Pass rate') }}</th></tr></thead>
                            <tbody>
                                @forelse ($result['byClass'] as $row)
                                    <tr><td>{{ $row['class_id'] ?? __('Unassigned') }}</td><td>{{ $row['candidates'] }}</td><td>{{ $row['pass_rate'] }}%</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No results yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ __('Historical comparison') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Series') }}</th><th>{{ __('Candidates') }}</th><th>{{ __('Pass rate') }}</th></tr></thead>
                            <tbody>
                                @forelse ($result['historical'] as $row)
                                    <tr><td>{{ $row['exam_series'] }}</td><td>{{ $row['candidates'] }}</td><td>{{ $row['pass_rate'] }}%</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No prior series.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
