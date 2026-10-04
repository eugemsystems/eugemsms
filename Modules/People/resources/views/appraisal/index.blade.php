<div>
    <h4 class="mb-1">{{ __('Appraisals') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">{{ __('New appraisal') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <select class="form-select form-select-sm @error('staffId') is-invalid @enderror" wire:model="staffId">
                                <option value="">{{ __('Staff member') }}</option>
                                @foreach ($staffList as $member)
                                    <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <select class="form-select form-select-sm @error('appraiserStaffId') is-invalid @enderror" wire:model="appraiserStaffId">
                                <option value="">{{ __('Appraiser') }}</option>
                                @foreach ($staffList as $member)
                                    <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <select class="form-select form-select-sm" wire:model="cycle">
                                <option value="probation">{{ __('Probation') }}</option>
                                <option value="mid_year">{{ __('Mid year') }}</option>
                                <option value="annual">{{ __('Annual') }}</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="create">{{ __('Start appraisal') }}</button>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('All appraisals') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Staff') }}</th><th>{{ __('Appraiser') }}</th><th>{{ __('Cycle') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($appraisals as $appraisal)
                                <tr>
                                    <td>{{ $appraisal->staff?->fullName() }}</td>
                                    <td>{{ $appraisal->appraiser?->fullName() }}</td>
                                    <td>{{ ucfirst($appraisal->cycle) }}</td>
                                    <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', ucfirst($appraisal->status)) }}</span></td>
                                    <td class="text-end"><a href="{{ route('people.appraisal.show', [$school, $appraisal]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Open') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No appraisals yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
