<div>
    <h4 class="mb-3">{{ __('Student wallets') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Balance') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($wallets as $wallet)
                                <tr wire:key="wallet-{{ $wallet->id }}" class="{{ $wallet->balance_minor < 0 ? 'table-danger' : '' }}">
                                    <td>{{ $wallet->student?->first_name }} {{ $wallet->student?->last_name }}</td>
                                    <td>{{ number_format($wallet->balance_minor / 100, 2) }} {{ $wallet->currency }}</td>
                                    <td><span class="badge {{ $wallet->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $wallet->status }}</span></td>
                                    <td><a href="{{ route('wallet.wallets.show', [$school, $wallet]) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Open') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No wallets yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New wallet') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="currency">
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create wallet') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
