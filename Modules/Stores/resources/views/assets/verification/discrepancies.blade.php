<div>
    <h4 class="mb-1">{{ __('Verification discrepancies') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A not-found asset can only leave the register through an approved write-off, by a different person from whoever recorded the scan.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Status') }}</th><th>{{ __('Note') }}</th><th>{{ __('Reason to write off') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($discrepancies as $verification)
                        <tr wire:key="disc-{{ $verification->id }}">
                            <td>{{ $verification->asset->name }} ({{ $verification->asset->asset_tag }})</td>
                            <td><span class="badge text-bg-{{ $verification->status === 'not_found' ? 'danger' : 'warning' }}">{{ $verification->status }}</span></td>
                            <td>{{ $verification->discrepancy_note }}</td>
                            <td><input type="text" class="form-control form-control-sm" wire:model="reasons.{{ $verification->id }}"></td>
                            <td>
                                @if ($verification->status === 'not_found')
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="writeOff({{ $verification->id }})">{{ __('Write off') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No discrepancies.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
