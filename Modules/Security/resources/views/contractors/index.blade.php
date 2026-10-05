<div>
    <h4 class="mb-1">{{ __('Contractors & gate') }}</h4>

    <div class="card mb-3" style="max-width:30rem">
        <div class="card-header">{{ __('Gate — sign in worker') }}</div>
        <div class="card-body">
            <select class="form-select mb-2" wire:model="gateWorkerId">
                <option value="">{{ __('Contractor worker') }}</option>
                @foreach ($workers as $worker)
                    <option value="{{ $worker->id }}">{{ $worker->full_name }} — {{ $worker->contractor->company_name }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-dark btn-sm w-100" wire:click="signIn">{{ __('Attempt sign-in') }}</button>

            @if ($gateResult !== null)
                <div class="alert mt-3 mb-0 py-3 text-center fw-bold {{ $gateResult === 'ALLOWED' ? 'alert-success' : 'alert-danger' }}" style="font-size:1.5rem">
                    {{ $gateResult }}
                </div>
                @if ($gateReason !== null)
                    <p class="small text-danger mt-2 mb-0">{{ $gateReason }}</p>
                @endif
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">{{ __('Signed in — not yet out') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Worker') }}</th><th>{{ __('Signed in') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($openVisits as $visit)
                        <tr wire:key="visit-{{ $visit->id }}">
                            <td>{{ $visit->contractorWorker->full_name }}</td>
                            <td>{{ $visit->signed_in_at->format('d M H:i') }}</td>
                            <td><button type="button" class="btn btn-outline-secondary btn-sm" wire:click="signOut({{ $visit->id }})">{{ __('Sign out') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No one signed in.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">{{ __('Contractors') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Status') }}</th><th>{{ __('Insurance expires') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($contractors as $contractor)
                                <tr wire:key="contractor-{{ $contractor->id }}">
                                    <td>{{ $contractor->company_name }}</td>
                                    <td>{{ $contractor->status }}</td>
                                    <td>{{ $contractor->insurance_expires_on?->toDateString() ?? '—' }}</td>
                                    <td>
                                        @if ($contractor->status === 'pending')
                                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="selectForApproval({{ $contractor->id }})">{{ __('Approve') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No contractors.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Workers') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Contractor') }}</th><th>{{ __('Police clearance') }}</th><th>{{ __('Cleared') }}</th></tr></thead>
                        <tbody>
                            @forelse ($workers as $worker)
                                <tr wire:key="worker-{{ $worker->id }}">
                                    <td>{{ $worker->full_name }}</td>
                                    <td>{{ $worker->contractor->company_name }}</td>
                                    <td>{{ $worker->police_clearance_on?->toDateString() ?? '—' }}</td>
                                    <td>{{ $worker->is_cleared ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No workers.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('New contractor') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="companyName" placeholder="{{ __('Company name') }}">
                    <input type="text" class="form-control mb-2" wire:model="contactPerson" placeholder="{{ __('Contact person (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="phone" placeholder="{{ __('Phone (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="workType" placeholder="{{ __('Work type (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createContractor">{{ __('Register contractor') }}</button>
                </div>
            </div>

            @if ($approvingContractorId !== null)
                <div class="card mb-3">
                    <div class="card-header">{{ __('Approve contractor #') }}{{ $approvingContractorId }}</div>
                    <div class="card-body">
                        <input type="date" class="form-control mb-2" wire:model="insuranceExpiresOn" placeholder="{{ __('Insurance expires') }}">
                        <input type="date" class="form-control mb-2" wire:model="safetyInductionOn" placeholder="{{ __('Safety induction on') }}">
                        <input type="date" class="form-control mb-2" wire:model="inductionValidUntil" placeholder="{{ __('Induction valid until') }}">
                        <input type="date" class="form-control mb-2" wire:model="policeClearanceOn" placeholder="{{ __('Police clearance (optional)') }}">
                        <button type="button" class="btn btn-success btn-sm" wire:click="approve">{{ __('Approve') }}</button>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">{{ __('New worker') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="workerContractorId">
                        <option value="">{{ __('Contractor') }}</option>
                        @foreach ($contractors as $contractor)
                            <option value="{{ $contractor->id }}">{{ $contractor->company_name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="workerFullName" placeholder="{{ __('Full name') }}">
                    <input type="text" class="form-control mb-2" wire:model="workerIdNumber" placeholder="{{ __('ID number (optional)') }}">
                    <input type="date" class="form-control mb-2" wire:model="workerInductionCompletedOn" placeholder="{{ __('Induction completed (optional)') }}">
                    <input type="date" class="form-control mb-2" wire:model="workerPoliceClearanceOn" placeholder="{{ __('Police clearance (optional) — required for site access') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createWorker">{{ __('Register worker') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
