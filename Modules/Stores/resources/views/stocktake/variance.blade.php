<div>
    <h4 class="mb-1">{{ __('Stock take variance') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A variance beyond threshold requires a recount by a different person before it can be approved.') }}</p>

    <div class="card">
        <div class="card-header d-flex gap-2 align-items-center">
            <select class="form-select form-select-sm" style="max-width: 20rem" wire:model.live="stockTakeId">
                <option value="">{{ __('Select stock take') }}</option>
                @foreach ($takes as $take)
                    <option value="{{ $take->id }}">{{ $take->take_number }}</option>
                @endforeach
            </select>
            <select class="form-select form-select-sm" style="max-width: 20rem" wire:model="shrinkageAccountId">
                <option value="">{{ __('Shrinkage account') }}</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-sm btn-primary" wire:click="approve">{{ __('Approve & post adjustment') }}</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Counted') }}</th><th>{{ __('Variance') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Recount') }}</th></tr></thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr wire:key="var-line-{{ $line->id }}">
                            <td>{{ $line->item->name }}</td>
                            <td>{{ $line->recount_quantity ?? $line->counted_quantity ?? '—' }}</td>
                            <td>
                                {{ $line->variance_quantity ?? '—' }}
                                @if ($line->requires_recount)
                                    <span class="badge text-bg-warning ms-1">{{ __('needs recount') }}</span>
                                @endif
                            </td>
                            <td class="d-flex gap-1">
                                <input type="text" class="form-control form-control-sm" wire:model="reasons.{{ $line->id }}" value="{{ $line->variance_reason }}" placeholder="{{ __('Reason') }}">
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="saveReason({{ $line->id }})">{{ __('Save') }}</button>
                            </td>
                            <td class="d-flex gap-1">
                                <input type="number" step="0.0001" class="form-control form-control-sm" style="max-width: 7rem" wire:model="recounts.{{ $line->id }}">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="recount({{ $line->id }})">{{ __('Recount') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Select a stock take.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
