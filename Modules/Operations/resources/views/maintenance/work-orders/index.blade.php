<div>
    <h4 class="mb-1">{{ __('Work orders') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Approve, complete, verify, and record parts/labour/contractor cost against a work order.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Title') }}</th><th>{{ __('Status') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Cost') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($workOrders as $wo)
                                <tr wire:key="wo-{{ $wo->id }}" class="{{ $selected?->id === $wo->id ? 'table-active' : '' }}">
                                    <td>{{ $wo->work_order_number }}</td>
                                    <td>{{ $wo->title }}</td>
                                    <td><span class="badge text-bg-secondary">{{ $wo->status }}</span></td>
                                    <td>{{ $wo->priority }}</td>
                                    <td>{{ number_format($wo->total_cost_minor / 100, 2) }} {{ $wo->currency }}</td>
                                    <td><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="select({{ $wo->id }})">{{ __('Open') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No work orders.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($selected)
                <div class="card mt-3">
                    <div class="card-header">{{ $selected->work_order_number }} — {{ $selected->status }}</div>
                    <div class="card-body">
                        <p>{{ $selected->description }}</p>

                        @if ($selected->status === 'pending_approval')
                            <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $selected->id }})">{{ __('Approve') }}</button>
                        @endif

                        @if (in_array($selected->status, ['approved', 'in_progress']))
                            <div class="mt-2">
                                <textarea class="form-control form-control-sm mb-1" wire:model="completionNotes" placeholder="{{ __('Completion notes') }}"></textarea>
                                <button type="button" class="btn btn-sm btn-primary" wire:click="complete({{ $selected->id }})">{{ __('Complete') }}</button>
                            </div>
                        @endif

                        @if ($selected->status === 'completed' && $selected->raised_by === auth()->id())
                            <button type="button" class="btn btn-sm btn-success mt-2" wire:click="verify({{ $selected->id }})">{{ __('Verify') }}</button>
                        @endif

                        <hr>
                        <h6>{{ __('Issue parts') }}</h6>
                        <div class="row g-2">
                            <div class="col-4">
                                <select class="form-select form-select-sm" wire:model="partsStoreId">
                                    <option value="">{{ __('Store') }}</option>
                                    @foreach ($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">
                                <select class="form-select form-select-sm" wire:model="partsItemId">
                                    <option value="">{{ __('Item') }}</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-2">
                                <input type="number" step="0.0001" class="form-control form-control-sm" wire:model="partsQuantity" placeholder="{{ __('Qty') }}">
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="issueParts({{ $selected->id }})">{{ __('Issue') }}</button>
                            </div>
                        </div>
                        <ul class="list-unstyled small mt-2">
                            @foreach ($selected->parts as $part)
                                <li>{{ $part->description }} — {{ $part->quantity }} {{ $part->unit }} — {{ number_format($part->line_cost_minor / 100, 2) }}</li>
                            @endforeach
                        </ul>

                        <hr>
                        <h6>{{ __('Record labour') }}</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <select class="form-select form-select-sm" wire:model="labourStaffId">
                                    <option value="">{{ __('Staff member') }}</option>
                                    @foreach ($staff as $member)
                                        <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-3">
                                <input type="number" step="0.25" class="form-control form-control-sm" wire:model="labourHours" placeholder="{{ __('Hours') }}">
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="recordLabour({{ $selected->id }})">{{ __('Record') }}</button>
                            </div>
                        </div>
                        <p class="small mt-1">{{ __('Labour hours so far:') }} {{ $selected->labour_hours }}</p>

                        @if ($selected->contractor_supplier_id)
                            <hr>
                            <h6>{{ __('Record contractor cost') }}</h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <select class="form-select form-select-sm" wire:model="contractorInvoiceId">
                                        <option value="">{{ __('Supplier invoice') }}</option>
                                        @foreach ($invoices as $invoice)
                                            <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="number" class="form-control form-control-sm" wire:model="contractorAmountMinor" placeholder="{{ __('Amount (minor)') }}">
                                </div>
                                <div class="col-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="recordContractorCost({{ $selected->id }})">{{ __('Record') }}</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New work order') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                    <textarea class="form-control mb-2" wire:model="description" rows="2" placeholder="{{ __('Description') }}"></textarea>
                    <select class="form-select mb-2" wire:model="maintenanceAssetId">
                        <option value="">{{ __('Asset (optional)') }}</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="workType">
                        @foreach (['corrective', 'preventive', 'improvement', 'inspection', 'emergency'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="priority">
                        @foreach (['emergency', 'high', 'normal', 'low'] as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="assignedTeam">
                        <option value="in_house">{{ __('In-house') }}</option>
                        <option value="contractor">{{ __('Contractor') }}</option>
                    </select>
                    @if ($assignedTeam === 'contractor')
                        <select class="form-select mb-2" wire:model="contractorSupplierId">
                            <option value="">{{ __('Contractor') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create work order') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
