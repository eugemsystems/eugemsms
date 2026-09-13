<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Currencies') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Which currencies :school transacts in, and which one is base.', ['school' => $school->name]) }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openEditModal">
            <i class="ri ri-add-line me-1"></i>{{ __('Register currency') }}
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Currency') }}</th>
                        <th>{{ __('Base') }}</th>
                        <th>{{ __('Accepted for payment') }}</th>
                        <th>{{ __('Cash rounding increment') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schoolCurrencies as $schoolCurrency)
                        @php $meta = $catalogue->get($schoolCurrency->currency); @endphp
                        <tr wire:key="school-currency-{{ $schoolCurrency->id }}">
                            <td>{{ $schoolCurrency->currency }} @if ($meta) — {{ $meta->name }} @endif</td>
                            <td>
                                @if ($schoolCurrency->is_base)
                                    <span class="badge text-bg-primary">{{ __('Base') }}</span>
                                @endif
                            </td>
                            <td>{{ $schoolCurrency->is_accepted_for_payment ? __('Yes') : __('No') }}</td>
                            <td>{{ $schoolCurrency->rounding_increment_minor }}</td>
                            <td>{{ $schoolCurrency->is_active ? __('Yes') : __('No') }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEditModal('{{ $schoolCurrency->currency }}')" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
                                    <i class="icon-base ri ri-pencil-line icon-22px"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No currencies registered yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showEditModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="save">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Register currency') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showEditModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('currency') is-invalid @enderror" id="currency" wire:model="currency">
                                        @foreach ($catalogue as $code => $meta)
                                            <option value="{{ $code }}">{{ $code }} — {{ $meta->name }}</option>
                                        @endforeach
                                    </select>
                                    <label for="currency">{{ __('Currency') }}</label>
                                    @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" min="1" class="form-control @error('roundingIncrementMinor') is-invalid @enderror" id="roundingIncrementMinor" wire:model="roundingIncrementMinor" placeholder=" ">
                                    <label for="roundingIncrementMinor">{{ __('Cash rounding increment (minor units)') }}</label>
                                    @error('roundingIncrementMinor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-text">{{ __('ZWG cash tenders round to this increment (BR-FIN-06-015). USD typically stays 1.') }}</div>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="isBase" wire:model="isBase">
                                <label class="form-check-label" for="isBase">{{ __('This is the school\'s base currency') }}</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="isAcceptedForPayment" wire:model="isAcceptedForPayment">
                                <label class="form-check-label" for="isAcceptedForPayment">{{ __('Accepted for payment') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
