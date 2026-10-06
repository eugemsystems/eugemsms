<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Withheld report cards') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Generated and stored, but not published: the learner owes more than the report threshold. Once the balance clears, publishing releases the card as it is.') }}</p>
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>{{ __('Learner') }}</th><th class="text-end">{{ __('Owed') }}</th><th class="text-end">{{ __('Threshold') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($results as $result)
                @php $gate = $balances[$result->id]; @endphp
                <tr wire:key="w-{{ $result->id }}">
                    <td>{{ $result->student?->fullName() }}</td>
                    <td class="text-end">{{ number_format($gate->gateBalanceMinor / 100, 2) }} {{ $gate->currency }}</td>
                    <td class="text-end">{{ number_format($gate->thresholdMinor / 100, 2) }}</td>
                    <td class="text-end">@if ($canOverride) <button type="button" class="btn btn-sm btn-outline-danger" wire:click="startOverride({{ $result->id }})">{{ __('Override') }}</button> @endif</td>
                </tr>
                @if ($overridingId === $result->id)
                    <tr wire:key="o-{{ $result->id }}"><td colspan="4">
                        <input type="text" class="form-control form-control-sm mb-2" wire:model="reason" placeholder="{{ __('Reason for releasing this report (recorded)') }}">
                        @error('reason') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                        <button type="button" class="btn btn-sm btn-danger" wire:click="override">{{ __('Grant override') }}</button>
                    </td></tr>
                @endif
            @empty
                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No withheld report cards this term.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div></div>
</div>
