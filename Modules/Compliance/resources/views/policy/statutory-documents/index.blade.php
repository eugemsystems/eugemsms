<div>
    <h4 class="mb-1">{{ __('Statutory documents & contracts') }}</h4>
    <p class="text-body-secondary small">{{ __('An expired operating licence, insurance or fire certificate raises a critical alert.') }}</p>

    <button type="button" class="btn btn-outline-info btn-sm mb-3" wire:click="checkExpiry">{{ __('Check expiry') }}</button>
    @if ($checked)
        <div class="alert {{ $criticalCount > 0 ? 'alert-danger' : 'alert-success' }}">{{ __('Critically expired documents:') }} {{ $criticalCount }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">{{ __('Statutory documents') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($documents as $document)
                                <tr wire:key="doc-{{ $document->id }}">
                                    <td>{{ str_replace('_', ' ', $document->document_type) }}</td>
                                    <td>{{ $document->expires_on?->toDateString() ?? '—' }}</td>
                                    <td><span class="badge {{ match ($document->status) { 'expired' => 'bg-danger', 'expiring' => 'bg-warning text-dark', default => 'bg-success' } }}">{{ $document->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No documents registered.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Register document') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="documentType">
                        <option value="registration_certificate">{{ __('Registration certificate') }}</option>
                        <option value="operating_licence">{{ __('Operating licence') }}</option>
                        <option value="insurance">{{ __('Insurance') }}</option>
                        <option value="tax_clearance">{{ __('Tax clearance') }}</option>
                        <option value="health_inspection">{{ __('Health inspection') }}</option>
                        <option value="fire_certificate">{{ __('Fire certificate') }}</option>
                        <option value="water_quality">{{ __('Water quality') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="issuingAuthority" placeholder="{{ __('Issuing authority') }}">
                    <input type="text" class="form-control mb-2" wire:model="referenceNumber" placeholder="{{ __('Reference number (optional)') }}">
                    <input type="date" class="form-control mb-2" wire:model="issuedOn" placeholder="{{ __('Issued on') }}">
                    <input type="date" class="form-control mb-2" wire:model="expiresOn" placeholder="{{ __('Expires on') }}">
                    <input type="number" class="form-control mb-2" wire:model="renewalLeadDays" placeholder="{{ __('Renewal lead days') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createDocument">{{ __('Register') }}</button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">{{ __('Contracts') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Counterparty') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($contracts as $contract)
                                <tr wire:key="contract-{{ $contract->id }}">
                                    <td>{{ $contract->counterparty_name }}</td>
                                    <td>{{ $contract->expires_on?->toDateString() ?? '—' }}</td>
                                    <td><span class="badge {{ match ($contract->status) { 'expired' => 'bg-danger', 'expiring' => 'bg-warning text-dark', default => 'bg-success' } }}">{{ $contract->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No contracts registered.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Register contract') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="counterpartyName" placeholder="{{ __('Counterparty name') }}">
                    <input type="text" class="form-control mb-2" wire:model="contractType" placeholder="{{ __('Contract type') }}">
                    <input type="date" class="form-control mb-2" wire:model="startsOn" placeholder="{{ __('Starts on') }}">
                    <input type="date" class="form-control mb-2" wire:model="contractExpiresOn" placeholder="{{ __('Expires on (optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="contractRenewalLeadDays" placeholder="{{ __('Renewal lead days') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createContract">{{ __('Register') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
