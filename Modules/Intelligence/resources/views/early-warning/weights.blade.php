<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('Indicator weights') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Re-weight or switch off the signals other modules provide. Changes apply from the next recompute; past scores are never rewritten.') }}</p>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Indicator') }}</th><th>{{ __('Source') }}</th><th style="width:8rem">{{ __('Weight (0–100)') }}</th><th>{{ __('On') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($indicators as $indicator)
                        <tr wire:key="w-{{ $indicator->key }}">
                            <td>{{ $indicator->plainLanguageDescription }}</td>
                            <td class="small text-body-secondary">{{ $indicator->moduleCode }}</td>
                            <td>
                                <input type="number" min="0" max="100" step="1" class="form-control form-control-sm" wire:model="rows.{{ $indicator->key }}.weight">
                                @error('rows.'.$indicator->key.'.weight') <div class="text-danger small">{{ $message }}</div> @enderror
                            </td>
                            <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" wire:model="rows.{{ $indicator->key }}.enabled"></div></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-primary" wire:click="save('{{ $indicator->key }}')">{{ __('Save') }}</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
