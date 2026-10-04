<div>
    <h4 class="mb-1">{{ __('Movement checkpoints') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A boundary checkpoint means crossing it = leaving campus.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Boundary') }}</th></tr></thead>
                        <tbody>
                            @forelse ($checkpoints as $checkpoint)
                                <tr>
                                    <td>{{ $checkpoint->code }}</td>
                                    <td>{{ $checkpoint->name }}</td>
                                    <td>{{ ucfirst($checkpoint->checkpoint_type) }}</td>
                                    <td>{{ $checkpoint->is_boundary ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No checkpoints yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New checkpoint') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                        <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                        <select class="form-select mb-2" wire:model="checkpointType">
                            <option value="gate">{{ __('Gate') }}</option>
                            <option value="building">{{ __('Building') }}</option>
                            <option value="zone">{{ __('Zone') }}</option>
                            <option value="dining">{{ __('Dining') }}</option>
                            <option value="sanatorium">{{ __('Sanatorium') }}</option>
                            <option value="transport">{{ __('Transport') }}</option>
                        </select>
                        <input type="text" class="form-control mb-2" wire:model="hardwareDeviceId" placeholder="{{ __('Hardware device ID (optional)') }}">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" wire:model="isBoundary" id="isBoundary">
                            <label class="form-check-label" for="isBoundary">{{ __('Boundary (crossing = leaving campus)') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Create') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
