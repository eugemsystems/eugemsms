<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Capture receipt') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Till :code — session :number', ['code' => $tillSession->till->code, 'number' => $tillSession->session_number]) }}</p>
        </div>
        <a href="{{ route('finance.till.cash-up', ['school' => $school, 'tillSession' => $tillSession]) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-lock-line me-1"></i>{{ __('Cash up & close till') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="studentSearch">{{ __('Learner (optional — leave blank for an unidentified deposit)') }}</label>
                        @if ($selectedStudentId !== null)
                            <div class="input-group">
                                <input type="text" class="form-control" value="{{ $selectedStudentLabel }}" disabled>
                                <button type="button" class="btn btn-outline-secondary" wire:click="clearStudent">{{ __('Change') }}</button>
                            </div>
                            @if ($studentBalance !== null)
                                <div class="form-text fs-6 fw-semibold {{ (float) str_replace(',', '', $studentBalance) > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ __('Outstanding balance: :currency :balance', ['currency' => $currency, 'balance' => $studentBalance]) }}
                                </div>
                            @endif
                        @else
                            <input type="text" class="form-control" id="studentSearch" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Search by admission number or name…') }}" autofocus>
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

                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('receiptType') is-invalid @enderror" id="receiptType" wire:model="receiptType">
                                <option value="fee">{{ __('Fee') }}</option>
                                <option value="tuckshop">{{ __('Tuckshop') }}</option>
                                <option value="hire">{{ __('Hire') }}</option>
                                <option value="sundry">{{ __('Sundry') }}</option>
                                <option value="deposit">{{ __('Deposit') }}</option>
                            </select>
                            <label for="receiptType">{{ __('Receipt type') }}</label>
                            @error('receiptType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('currency') is-invalid @enderror" id="currency" wire:model.live="currency">
                                <option value="USD">USD</option>
                                <option value="ZWG">ZWG</option>
                            </select>
                            <label for="currency">{{ __('Currency') }}</label>
                            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('payerName') is-invalid @enderror" id="payerName" wire:model="payerName" placeholder=" ">
                            <label for="payerName">{{ __('Payer name') }}</label>
                            @error('payerName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" id="payerPhone" wire:model="payerPhone" placeholder=" ">
                            <label for="payerPhone">{{ __('Payer phone (optional)') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" id="narration" wire:model="narration" placeholder=" ">
                            <label for="narration">{{ __('Narration (optional)') }}</label>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-2">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="min-width: 10rem;">{{ __('Tender') }}</th>
                                <th style="width: 10rem;">{{ __('Amount') }}</th>
                                <th style="min-width: 10rem;">{{ __('Reference') }}</th>
                                <th style="min-width: 10rem;">{{ __('Bank/settlement account') }}</th>
                                <th style="width: 3rem;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tenders as $index => $tender)
                                <tr wire:key="tender-{{ $index }}">
                                    <td>
                                        <select class="form-select form-select-sm" wire:model="tenders.{{ $index }}.tender_type">
                                            <option value="cash">{{ __('Cash') }}</option>
                                            <option value="bank_transfer">{{ __('Bank transfer') }}</option>
                                            <option value="card">{{ __('Card') }}</option>
                                            <option value="cheque">{{ __('Cheque') }}</option>
                                            <option value="ecocash">{{ __('EcoCash') }}</option>
                                            <option value="onemoney">{{ __('OneMoney') }}</option>
                                            <option value="innbucks">{{ __('InnBucks') }}</option>
                                            <option value="omari">{{ __("O'Mari") }}</option>
                                            <option value="zipit">{{ __('ZIPIT') }}</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" inputmode="decimal" class="form-control form-control-sm @error("tenders.{$index}.amount") is-invalid @enderror" wire:model="tenders.{{ $index }}.amount" placeholder="0.00">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" wire:model="tenders.{{ $index }}.reference" placeholder="{{ __('Optional') }}">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" wire:model="tenders.{{ $index }}.bank_account_id">
                                            <option value="">{{ __('Till default') }}</option>
                                            @foreach ($bankAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @if (count($tenders) > 1)
                                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeTender({{ $index }})" title="{{ __('Remove tender') }}" aria-label="{{ __('Remove tender') }}">
                                                <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary mb-4" wire:click="addTender">
                    <i class="ri ri-add-line me-1"></i>{{ __('Add tender') }}
                </button>

                @error('payerName')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Receipt & print') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
