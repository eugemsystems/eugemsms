<div>
    <h4 class="mb-1">{{ __('ZIMSEC results import') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('One row per line: candidate_number,subject_code,subject_name,grade,points — unmatched candidate numbers are reported, never dropped.') }}</p>

    <select class="form-select mb-3" style="max-width:420px" wire:model="registrationId">
        <option value="0">{{ __('Select registration') }}</option>
        @foreach ($registrations as $registration)
            <option value="{{ $registration->id }}">{{ $registration->exam_level }} — {{ $registration->exam_series }}</option>
        @endforeach
    </select>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('Import rows') }}</div>
                <div class="card-body">
                    <textarea class="form-control mb-2" rows="8" wire:model="rowsText" placeholder="CAND001,ENG,English Language,B,60"></textarea>
                    @error('rowsText') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="import">{{ __('Import') }}</button>

                    @if ($unmatched !== [])
                        <div class="alert alert-warning mt-3 mb-0 small">
                            <strong>{{ __('Unmatched rows:') }}</strong>
                            <ul class="mb-0">
                                @foreach ($unmatched as $row)
                                    <li>{{ $row['candidate_number'] }} / {{ $row['subject_code'] }} — {{ $row['reason'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Candidate') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Grade') }}</th></tr></thead>
                        <tbody>
                            @forelse ($results as $result)
                                <tr wire:key="result-{{ $result->id }}">
                                    <td>{{ $result->candidate_number }}</td>
                                    <td>{{ $result->subject_name }}</td>
                                    <td>{{ $result->grade }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No results imported yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
