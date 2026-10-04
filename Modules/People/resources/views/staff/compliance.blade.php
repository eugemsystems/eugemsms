<div>
    <h4 class="mb-1">{{ __('Document compliance') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Add a document') }}</div>
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
                            <select class="form-select form-select-sm" wire:model="documentType">
                                <option value="contract">{{ __('Contract') }}</option>
                                <option value="national_id">{{ __('National ID') }}</option>
                                <option value="police_clearance">{{ __('Police clearance') }}</option>
                                <option value="medical_certificate">{{ __('Medical certificate') }}</option>
                                <option value="teaching_certificate">{{ __('Teaching certificate') }}</option>
                                <option value="work_permit">{{ __('Work permit') }}</option>
                                <option value="drivers_licence">{{ __("Driver's licence") }}</option>
                                <option value="food_handler_certificate">{{ __('Food handler certificate') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <input type="file" class="form-control form-control-sm @error('file') is-invalid @enderror" wire:model="file">
                        </div>
                        <div class="col-12">
                            <input type="text" class="form-control form-control-sm" wire:model="referenceNumber" placeholder="{{ __('Reference number (optional)') }}">
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control form-control-sm" wire:model="issuedOn" placeholder="{{ __('Issued on') }}">
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control form-control-sm" wire:model="expiresOn" placeholder="{{ __('Expires on') }}">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="addDocument" wire:loading.attr="disabled">{{ __('Add document') }}</button>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Expiring documents') }}</div>
                <p class="text-body-secondary small px-3 pt-3 mb-0">{{ __('Police clearance, medical certificate, and work permit are compliance-critical (BR-PPL-04-018).') }}</p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Staff') }}</th><th>{{ __('Type') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Days left') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($expiring as $row)
                                <tr>
                                    <td>{{ $row['document']->staff?->fullName() }}</td>
                                    <td>{{ str_replace('_', ' ', ucfirst($row['document']->document_type)) }}</td>
                                    <td>{{ $row['document']->expires_on?->format('d M Y') }}</td>
                                    <td class="{{ $row['daysRemaining'] < 0 ? 'text-danger' : '' }}">{{ $row['daysRemaining'] }}</td>
                                    <td>@if ($row['isComplianceCritical']) <span class="badge text-bg-danger">{{ __('Compliance critical') }}</span> @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('Nothing expiring within the configured warning window.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
