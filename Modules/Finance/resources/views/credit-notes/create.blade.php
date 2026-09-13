<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('New credit note') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Posts Dr Fee Income / Cr Fee Debtors — never a receipt, so it never appears in collections.') }}</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="mb-3">
                    <label class="form-label" for="studentSearch">{{ __('Learner') }}</label>
                    @if ($selectedStudentId !== null)
                        <div class="input-group">
                            <input type="text" class="form-control" value="{{ $selectedStudentLabel }}" disabled>
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('selectedStudentId', null)">{{ __('Change') }}</button>
                        </div>
                    @else
                        <input type="text" class="form-control" id="studentSearch" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Search by admission number or name…') }}">
                        @if ($studentSearch !== '')
                            <div class="list-group mt-1">
                                @forelse ($this->studentResults() as $result)
                                    <button type="button" wire:key="result-{{ $result->id }}" class="list-group-item list-group-item-action" wire:click="selectStudent({{ $result->id }})">
                                        {{ $result->admission_number }} — {{ $result->fullName() }}
                                    </button>
                                @empty
                                    <div class="list-group-item text-body-secondary">{{ __('No matches.') }}</div>
                                @endforelse
                            </div>
                        @endif
                    @endif
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="invoiceId" wire:model="invoiceId">
                                <option value="">{{ __('Standalone (no invoice)') }}</option>
                                @foreach ($invoices as $invoice)
                                    <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} ({{ number_format($invoice->balance_minor / 100, 2) }} {{ $invoice->currency }})</option>
                                @endforeach
                            </select>
                            <label for="invoiceId">{{ __('Against invoice (optional)') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('reasonCode') is-invalid @enderror" id="reasonCode" wire:model="reasonCode">
                                @foreach (['subject_dropped', 'withdrawal', 'billing_error', 'residency_change', 'goodwill', 'overcharge'] as $code)
                                    <option value="{{ $code }}">{{ \Illuminate\Support\Str::headline($code) }}</option>
                                @endforeach
                            </select>
                            <label for="reasonCode">{{ __('Reason code') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="currency" wire:model="currency">
                                <option value="USD">USD</option>
                                <option value="ZWG">ZWG</option>
                            </select>
                            <label for="currency">{{ __('Currency') }}</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating form-floating-outline">
                            <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" wire:model="reason" style="height: 6rem;" placeholder=" "></textarea>
                            <label for="reason">{{ __('Reason (free text)') }}</label>
                            @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-2">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="min-width: 12rem;">{{ __('Component') }}</th>
                                <th style="min-width: 12rem;">{{ __('Description') }}</th>
                                <th style="width: 8rem;">{{ __('Amount') }}</th>
                                <th style="width: 3rem;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $index => $line)
                                <tr wire:key="cn-line-{{ $index }}">
                                    <td>
                                        <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.component_id">
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach ($components as $component)
                                                <option value="{{ $component->id }}">{{ $component->code }} — {{ $component->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" wire:model="lines.{{ $index }}.description">
                                    </td>
                                    <td>
                                        <input type="text" inputmode="decimal" class="form-control form-control-sm" wire:model="lines.{{ $index }}.amount_minor" placeholder="0.00">
                                    </td>
                                    <td>
                                        @if (count($lines) > 1)
                                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeLine({{ $index }})">
                                                <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary mb-4" wire:click="addLine">
                    <i class="ri ri-add-line me-1"></i>{{ __('Add line') }}
                </button>

                @if ($this->canSelfApprove())
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="selfApprove" wire:model="selfApprove">
                        <label class="form-check-label" for="selfApprove">{{ __('I am approving this credit note (needed above the approval threshold)') }}</label>
                    </div>
                @endif

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create credit note') }}</button>
            </form>
        </div>
    </div>
</div>
