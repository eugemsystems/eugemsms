<div>
    <h4 class="mb-1">{{ __('Asset verification') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A not-found asset is flagged for investigation — it is never quietly removed.') }}</p>

    <div class="card mb-4" style="max-width: 36rem">
        <div class="card-body d-flex gap-2">
            <input type="text" class="form-control" wire:model="verificationRound" placeholder="{{ __('Round label') }}">
            <select class="form-select" wire:model="categoryId">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-primary" wire:click="createRound">{{ __('Start round') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Pending scans — :round', ['round' => $verificationRound]) }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Expected location') }}</th><th>{{ __('Actual location') }}</th><th>{{ __('Condition') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pending as $verification)
                        <tr wire:key="verif-{{ $verification->id }}">
                            <td>{{ $verification->asset->name }} ({{ $verification->asset->asset_tag }})</td>
                            <td>{{ $verification->asset->location }}</td>
                            <td><input type="text" class="form-control form-control-sm" wire:model="actualLocations.{{ $verification->id }}"></td>
                            <td>
                                <select class="form-select form-select-sm" wire:model="conditions.{{ $verification->id }}">
                                    <option value="">—</option>
                                    <option value="good">good</option>
                                    <option value="fair">fair</option>
                                    <option value="poor">poor</option>
                                </select>
                            </td>
                            <td class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-success" wire:click="scan({{ $verification->id }}, true)">{{ __('Found') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="scan({{ $verification->id }}, false)">{{ __('Not found') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Nothing pending — start a round above.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
