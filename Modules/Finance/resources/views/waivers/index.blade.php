<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Waivers & write-offs') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('A waiver reduces the amount owed before it is chased; a write-off recognises it as uncollectable after.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openRequestModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New request') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$waivers"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($waivers as $waiver)
            <tr wire:key="waiver-{{ $waiver->id }}">
                @if ($this->columnVisible('type'))
                    <td>
                        {{ \Illuminate\Support\Str::headline($waiver->type) }}
                        <div class="text-body-secondary small">{{ $waiver->student->admission_number }} — {{ $waiver->student->fullName() }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('amount_minor'))
                    <td>{{ number_format($waiver->amount_minor / 100, 2) }} {{ $waiver->currency }}</td>
                @endif
                @if ($this->columnVisible('reason_code'))
                    <td>{{ \Illuminate\Support\Str::headline($waiver->reason_code) }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge {{ match ($waiver->status) { 'posted' => 'text-bg-success', 'rejected' => 'text-bg-danger', default => 'text-bg-warning' } }}">
                            {{ \Illuminate\Support\Str::headline($waiver->status) }}
                        </span>
                    </td>
                @endif
                <td class="text-end">
                    @if ($waiver->status === 'pending')
                        <div class="d-flex justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-success" wire:click="openApproveModal({{ $waiver->id }})">{{ __('Approve') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reject({{ $waiver->id }})" wire:confirm="{{ __('Reject this request?') }}">{{ __('Reject') }}</button>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No requests yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($showRequestModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="request">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New waiver / write-off request') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showRequestModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Learner') }}</label>
                                @if ($selectedStudentId !== null)
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="{{ $selectedStudentLabel }}" disabled>
                                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('selectedStudentId', null)">{{ __('Change') }}</button>
                                    </div>
                                @else
                                    <input type="text" class="form-control" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Search…') }}">
                                    @if ($studentSearch !== '')
                                        <div class="list-group mt-1">
                                            @forelse ($this->studentResults() as $result)
                                                <button type="button" wire:key="w-result-{{ $result->id }}" class="list-group-item list-group-item-action" wire:click="selectStudent({{ $result->id }})">
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
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="w-type" wire:model="type">
                                            <option value="waiver">{{ __('Waiver') }}</option>
                                            <option value="write_off">{{ __('Write-off') }}</option>
                                        </select>
                                        <label for="w-type">{{ __('Type') }}</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="w-invoice" wire:model="invoiceId">
                                            <option value="">{{ __('Not tied to a specific invoice') }}</option>
                                            @foreach ($invoices as $invoice)
                                                <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} ({{ number_format($invoice->balance_minor / 100, 2) }})</option>
                                            @endforeach
                                        </select>
                                        <label for="w-invoice">{{ __('Invoice (optional)') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" inputmode="decimal" class="form-control @error('amount') is-invalid @enderror" id="w-amount" wire:model="amount" placeholder=" ">
                                        <label for="w-amount">{{ __('Amount') }}</label>
                                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="w-currency" wire:model="currency">
                                            <option value="USD">USD</option>
                                            <option value="ZWG">ZWG</option>
                                        </select>
                                        <label for="w-currency">{{ __('Currency') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="w-reason-code" wire:model="reasonCode">
                                            @foreach (['hardship', 'orphan', 'staff_child', 'uncollectable', 'deceased', 'goodwill'] as $code)
                                                <option value="{{ $code }}">{{ \Illuminate\Support\Str::headline($code) }}</option>
                                            @endforeach
                                        </select>
                                        <label for="w-reason-code">{{ __('Reason code') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating form-floating-outline">
                                        <textarea class="form-control @error('reason') is-invalid @enderror" id="w-reason" wire:model="reason" style="height: 6rem;" placeholder=" "></textarea>
                                        <label for="w-reason">{{ __('Reason') }}</label>
                                        @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showRequestModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Submit request') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($approvingWaiverId !== null)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="approve">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Approve & post') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('approvingWaiverId', null)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('contraAccountId') is-invalid @enderror" id="contraAccountId" wire:model="contraAccountId">
                                        <option value="">{{ __('Select an account') }}</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    <label for="contraAccountId">{{ __('Contra account (e.g. Bad Debt Expense)') }}</label>
                                    @error('contraAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('debtorAccountId') is-invalid @enderror" id="debtorAccountId" wire:model="debtorAccountId">
                                        <option value="">{{ __('Select an account') }}</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    <label for="debtorAccountId">{{ __('Debtor account being relieved') }}</label>
                                    @error('debtorAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('approvingWaiverId', null)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Approve & post') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
