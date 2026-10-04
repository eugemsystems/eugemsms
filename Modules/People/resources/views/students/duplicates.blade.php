<div>
    <h4 class="mb-1">{{ __('Duplicate learner scan') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Checks a name, date of birth, and identifiers against existing learners. Nothing here merges records automatically.') }}</p>

    <div class="card mb-4">
        <div class="card-body">
            <form wire:submit="scan">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('firstName') is-invalid @enderror" wire:model="firstName" placeholder=" ">
                            <label>{{ __('First name') }}</label>
                            @error('firstName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('lastName') is-invalid @enderror" wire:model="lastName" placeholder=" ">
                            <label>{{ __('Last name') }}</label>
                            @error('lastName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('dateOfBirth') is-invalid @enderror" wire:model="dateOfBirth">
                            <label>{{ __('Date of birth') }}</label>
                            @error('dateOfBirth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="nationalRegistrationNo" placeholder=" ">
                            <label>{{ __('National reg. # (optional)') }}</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="birthCertificateNo" placeholder=" ">
                            <label>{{ __('Birth cert. # (optional)') }}</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">{{ __('Scan') }}</button>
            </form>
        </div>
    </div>

    @if ($hasScanned)
        @if ($results === [])
            <div class="alert alert-success">{{ __('No possible duplicates found.') }}</div>
        @else
            <div class="card">
                <div class="card-header">{{ __(':count possible match(es)', ['count' => count($results)]) }}</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>{{ __('Admission #') }}</th><th>{{ __('Name') }}</th><th>{{ __('Matched on') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($results as $candidate)
                                @php $match = $students->get($candidate['studentId']); @endphp
                                <tr>
                                    <td>{{ $candidate['admissionNumber'] }}</td>
                                    <td>{{ $match?->fullName() }}</td>
                                    <td>{{ str_replace('_', ' ', $candidate['matchedOn']) }}</td>
                                    <td class="text-end">
                                        @if ($match)
                                            <a href="{{ route('people.students.show', [$school, $match]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('View') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
