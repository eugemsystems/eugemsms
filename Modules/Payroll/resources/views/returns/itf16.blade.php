<div>
    <h4 class="mb-1">{{ __('ITF16 annual reconciliation') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Reconciles every payslip\'s PAYE for the tax year against the twelve monthly P2 returns already on file. A mismatch blocks preparation and names the discrepancy.') }}</p>

    <div class="card mb-3" style="max-width:24rem">
        <div class="card-body">
            <input type="number" class="form-control mb-2" wire:model="taxYear" placeholder="{{ __('Tax year') }}">
            <button type="button" class="btn btn-primary btn-sm" wire:click="prepare">{{ __('Prepare ITF16') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Year') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($itf16Returns as $return)
                        <tr wire:key="itf16-{{ $return->id }}">
                            <td>{{ $return->period_reference }}</td>
                            <td>{{ number_format($return->amount_due_minor / 100, 2) }} {{ $return->currency }}</td>
                            <td><span class="badge bg-secondary">{{ $return->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No ITF16 returns prepared yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
