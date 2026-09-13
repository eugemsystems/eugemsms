<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Balance integrity') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Compares the cached account_balances against the same figures computed fresh from journal_lines.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="rebuild" wire:confirm="{{ __('Rebuild every account balance from source now?') }}" wire:loading.attr="disabled">
            <i class="ri ri-refresh-line me-1"></i>{{ __('Rebuild from source') }}
        </button>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">{{ __('Mismatches') }}</h6>
            <span class="badge {{ count($mismatches) === 0 ? 'text-bg-success' : 'text-bg-danger' }}">
                {{ count($mismatches) === 0 ? __('Cache matches source') : __(':count mismatch(es) found', ['count' => count($mismatches)]) }}
            </span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Account') }}</th>
                        <th>{{ __('Term') }}</th>
                        <th>{{ __('Currency') }}</th>
                        <th class="text-end">{{ __('Cached closing') }}</th>
                        <th class="text-end">{{ __('Source closing') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mismatches as $mismatch)
                        <tr wire:key="mismatch-{{ $mismatch['account_id'] }}-{{ $mismatch['term_id'] }}-{{ $mismatch['currency'] }}">
                            <td>
                                @php $account = $accountsById->get($mismatch['account_id']); @endphp
                                {{ $account?->code }} — {{ $account?->name }}
                            </td>
                            <td>{{ $mismatch['term_id'] }}</td>
                            <td>{{ $mismatch['currency'] }}</td>
                            <td class="text-end">{{ number_format($mismatch['cached_closing_minor'] / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($mismatch['source_closing_minor'] / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No mismatches — the cache agrees with source for every account.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
