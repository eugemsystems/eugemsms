<div>
    <h4 class="mb-1">{{ __('Admissions intakes') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Open intakes') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Grade') }}</th><th>{{ __('Window') }}</th><th>{{ __('Places') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($intakes as $intake)
                                <tr>
                                    <td>{{ $intake->name }}</td>
                                    <td>{{ $intake->gradeLevel?->name }}</td>
                                    <td>{{ $intake->opens_on->format('d M Y') }} – {{ $intake->closes_on->format('d M Y') }}</td>
                                    <td>{{ $intake->places_accepted }} / {{ $intake->target_places }} <span class="text-body-secondary">({{ $intake->places_offered }} {{ __('offered') }})</span></td>
                                    <td><span class="badge text-bg-{{ $intake->status === 'open' ? 'success' : 'secondary' }}">{{ ucfirst($intake->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No intakes yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New intake') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="academicYearId">
                                        @foreach ($academicYears as $year)
                                            <option value="{{ $year->id }}">{{ $year->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Academic year') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('gradeLevelId') is-invalid @enderror" wire:model="gradeLevelId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($gradeLevels as $gradeLevel)
                                            <option value="{{ $gradeLevel->id }}">{{ $gradeLevel->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Grade level') }}</label>
                                    @error('gradeLevelId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control @error('opensOn') is-invalid @enderror" wire:model="opensOn">
                                    <label>{{ __('Opens on') }}</label>
                                    @error('opensOn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control @error('closesOn') is-invalid @enderror" wire:model="closesOn">
                                    <label>{{ __('Closes on') }}</label>
                                    @error('closesOn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control @error('targetPlaces') is-invalid @enderror" wire:model="targetPlaces" placeholder=" ">
                                    <label>{{ __('Target places') }}</label>
                                    @error('targetPlaces') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="depositDeadlineDays" placeholder=" ">
                                    <label>{{ __('Deposit deadline (days)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('applicationFeeAmount') is-invalid @enderror" wire:model="applicationFeeAmount" placeholder=" ">
                                    <label>{{ __('Application fee (optional)') }}</label>
                                    @error('applicationFeeAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="applicationFeeCurrency" placeholder=" ">
                                    <label>{{ __('Currency') }}</label>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('acceptanceDepositAmount') is-invalid @enderror" wire:model="acceptanceDepositAmount" placeholder=" ">
                                    <label>{{ __('Acceptance deposit (optional)') }}</label>
                                    @error('acceptanceDepositAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="acceptanceDepositCurrency" placeholder=" ">
                                    <label>{{ __('Currency') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create intake') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
