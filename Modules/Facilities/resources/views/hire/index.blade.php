<div>
    <h4 class="mb-1">{{ __('External hire') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Resource') }}</th><th>{{ __('Hirer') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($bookings as $booking)
                                <tr wire:key="hire-{{ $booking->id }}" class="{{ $selectedBookingId === $booking->id ? 'table-active' : '' }}">
                                    <td>{{ $booking->resource->name }}</td>
                                    <td>{{ $booking->hirer_name }}</td>
                                    <td>{{ $booking->status }}</td>
                                    <td class="d-flex gap-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="select({{ $booking->id }})">{{ __('Select') }}</button>
                                        @if ($booking->status === 'requested')
                                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="approve({{ $booking->id }})">{{ __('Approve') }}</button>
                                        @endif
                                        @if ($booking->status === 'approved')
                                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="confirm({{ $booking->id }})">{{ __('Confirm') }}</button>
                                        @endif
                                        @if (in_array($booking->status, ['confirmed', 'in_progress'], true))
                                            <button type="button" class="btn btn-outline-dark btn-sm" wire:click="complete({{ $booking->id }})">{{ __('Complete') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No external hires.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            @if ($selectedBookingId !== null)
                <div class="card mb-3">
                    <div class="card-header">{{ __('Record deposit for booking #') }}{{ $selectedBookingId }}</div>
                    <div class="card-body">
                        <input type="number" class="form-control mb-2" wire:model="depositAmountMinor" placeholder="{{ __('Deposit amount (minor units)') }}">
                        <select class="form-select mb-2" wire:model="cashAccountId">
                            <option value="">{{ __('Cash/bank account') }}</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="depositsHeldLiabilityAccountId">
                            <option value="">{{ __('Deposits held liability account') }}</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="recordDeposit">{{ __('Record deposit') }}</button>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">{{ __('Assess damage & refund deposit') }}</div>
                    <div class="card-body">
                        <input type="number" class="form-control mb-2" wire:model="damageDeductedMinor" placeholder="{{ __('Damage deducted (minor units)') }}">
                        <select class="form-select mb-2" wire:model="damageRecoveryIncomeAccountId">
                            <option value="">{{ __('Damage recovery income account (if any deduction)') }}</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <textarea class="form-control mb-2" wire:model="damageAssessmentNote" placeholder="{{ __('Assessment note (required if any amount is deducted)') }}"></textarea>
                        <button type="button" class="btn btn-danger btn-sm" wire:click="assessAndRefund">{{ __('Assess & refund') }}</button>
                    </div>
                </div>
            @else
                <div class="text-body-secondary">{{ __('Select a booking to record a deposit or refund it.') }}</div>
            @endif
        </div>
    </div>
</div>
