<div>
    <h4 class="mb-3">{{ __('Staff loans') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Staff') }}</th><th>{{ __('Type') }}</th><th>{{ __('Principal') }}</th><th>{{ __('Outstanding') }}</th><th>{{ __('Instalment') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($loans as $loan)
                                <tr wire:key="loan-{{ $loan->id }}">
                                    <td>{{ $loan->staff_id }}</td>
                                    <td>{{ $loan->loan_type }}</td>
                                    <td>{{ number_format($loan->principal_minor / 100, 2) }} {{ $loan->currency }}</td>
                                    <td>{{ number_format($loan->outstanding_minor / 100, 2) }}</td>
                                    <td>{{ number_format($loan->instalment_minor / 100, 2) }}</td>
                                    <td><span class="badge {{ $loan->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $loan->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No staff loans yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New loan') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="staffId">
                        <option value="">{{ __('Staff member') }}</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model.live="loanType">
                        <option value="salary_advance">{{ __('Salary advance') }}</option>
                        <option value="staff_loan">{{ __('Staff loan') }}</option>
                        <option value="fee_offset">{{ __('Fee offset (staff-child)') }}</option>
                        <option value="equipment">{{ __('Equipment') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="principalMinor" placeholder="{{ __('Principal (minor units)') }}">
                    <select class="form-select mb-2" wire:model="currency">
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="interestRatePercent" placeholder="{{ __('Interest rate percent') }}">
                    <input type="number" class="form-control mb-2" wire:model="instalmentMinor" placeholder="{{ __('Instalment (minor units)') }}">
                    <input type="number" class="form-control mb-2" wire:model="instalmentCount" placeholder="{{ __('Number of instalments') }}">
                    <input type="date" class="form-control mb-2" wire:model="startsOn">
                    @if ($loanType === 'fee_offset')
                        <input type="text" class="form-control mb-2" wire:model="offsetStudentIds" placeholder="{{ __('Learner id(s), comma separated') }}">
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create loan') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
