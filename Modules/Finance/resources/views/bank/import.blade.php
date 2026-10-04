<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Import bank statement') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Upload a CSV export from your bank and map its columns — exact-reference auto-matching happens straight away; everything else is confirmed by hand in the matching workbench next.') }}</p>
    </div>

    <form wire:submit="import">
        <div class="card mb-3">
            <div class="card-header">{{ __('Statement details') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('bankAccountId') is-invalid @enderror" wire:model="bankAccountId">
                                <option value="">{{ __('Select an account') }}</option>
                                @foreach ($bankAccounts as $bankAccount)
                                    <option value="{{ $bankAccount->id }}">{{ $bankAccount->bank_name }} — {{ $bankAccount->account_name }}</option>
                                @endforeach
                            </select>
                            <label>{{ __('Bank account') }}</label>
                            @error('bankAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('statementFrom') is-invalid @enderror" wire:model="statementFrom">
                            <label>{{ __('From') }}</label>
                            @error('statementFrom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('statementTo') is-invalid @enderror" wire:model="statementTo">
                            <label>{{ __('To') }}</label>
                            @error('statementTo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('openingBalance') is-invalid @enderror" wire:model="openingBalance" placeholder=" ">
                            <label>{{ __('Opening balance') }}</label>
                            @error('openingBalance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('closingBalance') is-invalid @enderror" wire:model="closingBalance" placeholder=" ">
                            <label>{{ __('Closing balance') }}</label>
                            @error('closingBalance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{{ __('File') }}</div>
            <div class="card-body">
                <input type="file" class="form-control @error('file') is-invalid @enderror" wire:model="file" accept=".csv">
                @error('file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <div wire:loading wire:target="file" class="text-body-secondary small mt-1">{{ __('Reading file…') }}</div>
            </div>
        </div>

        @if ($sourceHeaders !== [])
            <div class="card mb-3">
                <div class="card-header">{{ __('Map columns') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" wire:model="dateColumn">
                                    <option value="">{{ __('Not mapped') }}</option>
                                    @foreach ($sourceHeaders as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Transaction date') }} *</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" wire:model="valueDateColumn">
                                    <option value="">{{ __('Not mapped') }}</option>
                                    @foreach ($sourceHeaders as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Value date (optional)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" wire:model="referenceColumn">
                                    <option value="">{{ __('Not mapped') }}</option>
                                    @foreach ($sourceHeaders as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Reference (optional)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" wire:model="descriptionColumn">
                                    <option value="">{{ __('Not mapped') }}</option>
                                    @foreach ($sourceHeaders as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Description') }} *</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" wire:model="debitColumn">
                                    <option value="">{{ __('Not mapped') }}</option>
                                    @foreach ($sourceHeaders as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Debit') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" wire:model="creditColumn">
                                    <option value="">{{ __('Not mapped') }}</option>
                                    @foreach ($sourceHeaders as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Credit') }}</label>
                            </div>
                        </div>
                    </div>
                    <p class="text-body-secondary small mb-0 mt-2">{{ __('Map at least a debit or a credit column.') }}</p>

                    <h6 class="mt-4">{{ __('Preview') }} ({{ count($parsedRows) }} {{ __('rows') }})</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    @foreach ($sourceHeaders as $header)
                                        <th>{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($parsedRows, 0, 5) as $row)
                                    <tr>
                                        @foreach ($sourceHeaders as $header)
                                            <td>{{ $row[$header] ?? '' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" @if ($sourceHeaders === []) disabled @endif>
            {{ __('Import statement') }}
        </button>
    </form>
</div>
