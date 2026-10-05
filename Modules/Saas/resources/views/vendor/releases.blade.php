<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Releases') }}</h4><p class="text-body-secondary small mb-0">{{ __('Deploy to named canary tenants first. Rollback is available until you confirm a release stable — then it is gone.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Version') }}</th><th>{{ __('Stage') }}</th><th>{{ __('Canary tenants') }}</th><th>{{ __('Migration') }}</th><th>{{ __('Rollback') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($releases as $release)
                        <tr wire:key="rel-{{ $release->id }}">
                            <td>{{ $release->version }}</td><td>{{ __(ucfirst($release->deployment_stage)) }}</td>
                            <td class="small">{{ collect($release->canary_tenant_ids ?? [])->map(fn ($id) => $tenantNames[$id] ?? $id)->implode(', ') }}</td>
                            <td>{{ __(ucfirst(str_replace('_', ' ', $release->migration_status))) }}</td>
                            <td>{{ $release->rollback_available && $release->migration_status !== 'rolled_back' ? __('Available') : __('Closed') }}</td>
                            <td class="text-end text-nowrap">
                                @if ($release->rollback_available && $release->migration_status !== 'rolled_back')
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="confirmStable({{ $release->id }})" wire:confirm="{{ __('Confirm stable? Rollback will no longer be possible.') }}">{{ __('Confirm stable') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="rollback({{ $release->id }})" wire:confirm="{{ __('Roll this release back?') }}">{{ __('Roll back') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No releases.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Start a canary') }}</div><div class="card-body">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="version" placeholder="1.4.0">
            @error('version') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select multiple class="form-select form-select-sm mb-2" size="6" wire:model="canaryTenantIds">@foreach ($tenants as $tenant) <option value="{{ $tenant->id }}">{{ $tenant->name }}</option> @endforeach</select>
            @error('canaryTenantIds') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="startCanary">{{ __('Start canary') }}</button>
        </div></div></div>
    </div>
</div>
