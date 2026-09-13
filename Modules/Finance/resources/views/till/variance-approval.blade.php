<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Variance approval') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Till sessions awaiting supervisor sign-off because the declared count is outside tolerance.') }}</p>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Session') }}</th>
                        <th>{{ __('Till') }}</th>
                        <th>{{ __('Cashier') }}</th>
                        <th>{{ __('Opened') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->pendingSessions() as $session)
                        <tr wire:key="session-{{ $session->id }}">
                            <td>{{ $session->session_number }}</td>
                            <td>{{ $session->till->code }}</td>
                            <td>{{ $session->cashier->name }}</td>
                            <td>{{ $session->opened_at->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="review({{ $session->id }})">{{ __('Review') }}</button>
                            </td>
                        </tr>
                        @if ($reviewingSessionId === $session->id)
                            <tr>
                                <td colspan="5">
                                    <div class="card bg-body-secondary mb-2">
                                        <div class="card-body">
                                            <form wire:submit="approve">
                                                <div class="form-floating form-floating-outline mb-3">
                                                    <textarea class="form-control @error('varianceReason') is-invalid @enderror" id="varianceReason" wire:model="varianceReason" style="height: 6rem;" placeholder=" "></textarea>
                                                    <label for="varianceReason">{{ __('Sign-off reason') }}</label>
                                                    @error('varianceReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">{{ __('Sign off & close session') }}</button>
                                                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('reviewingSessionId', null)">{{ __('Cancel') }}</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No sessions are awaiting sign-off.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
