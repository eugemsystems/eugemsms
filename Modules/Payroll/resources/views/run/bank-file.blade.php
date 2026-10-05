<div>
    <h4 class="mb-1">{{ __('Payroll bank file') }}</h4>
    <p class="text-body-secondary small">{{ __('Generates a bank payment file from a posted run and records the payment.') }}</p>

    <div class="card" style="max-width:40rem">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Run #') }}</th><th>{{ __('Period') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($postedRuns as $run)
                        <tr wire:key="bank-run-{{ $run->id }}" class="{{ $selectedRunId === $run->id ? 'table-active' : '' }}">
                            <td>{{ $run->run_number }}</td>
                            <td>{{ $run->period_month }}</td>
                            <td><span class="badge {{ $run->status === 'paid' ? 'bg-success' : 'bg-secondary' }}">{{ $run->status }}</span></td>
                            <td>
                                @if ($run->status === 'posted')
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="select({{ $run->id }})">{{ __('Select') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No posted runs yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($selectedRunId)
            <div class="card-footer">
                <button type="button" class="btn btn-primary btn-sm" wire:click="generateAndRecord">{{ __('Generate bank file & mark paid') }}</button>
            </div>
        @endif
    </div>
</div>
