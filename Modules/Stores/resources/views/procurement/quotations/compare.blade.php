<div>
    <h4 class="mb-1">{{ __('Compare quotations') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Awarding anything other than the lowest compliant bid requires a written justification.') }}</p>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('1. Request quotations') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="requisitionId">
                        <option value="">{{ __('Requisition') }}</option>
                        @foreach ($requisitions as $req)
                            <option value="{{ $req->id }}">{{ $req->requisition_number }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" multiple wire:model="invitedSupplierIds" size="5">
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="closesOn">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createRequest">{{ __('Create request') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Open requests') }}</div>
                <div class="list-group list-group-flush">
                    @foreach ($requests as $request)
                        <button type="button" class="list-group-item list-group-item-action" wire:click="$set('activeRequestId', {{ $request->id }})">{{ $request->request_number }} — {{ $request->status }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('2. Record a quotation') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="recordSupplierId">
                        <option value="">{{ __('Supplier') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="recordTotalMinor" placeholder="{{ __('Total (minor units)') }}">
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="recordQuotation">{{ __('Record') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('3. Compare and award') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="justification" placeholder="{{ __('Justification (required if not lowest)') }}">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($quotations as $quotation)
                                    <tr wire:key="quote-{{ $quotation->id }}">
                                        <td>{{ $quotation->supplier->name }}</td>
                                        <td>{{ number_format($quotation->total_minor / 100, 2) }} {{ $quotation->currency }}</td>
                                        <td>{{ $quotation->status }}</td>
                                        <td><button type="button" class="btn btn-sm btn-success" wire:click="award({{ $quotation->id }})">{{ __('Award') }}</button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Select a request to see its quotations.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
