<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('users.show', $user) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Direct permissions') }}</h4>
            <span class="text-body-secondary small">{{ __(':name in :school — granted here regardless of any role.', ['name' => trim($user->first_name.' '.$user->last_name), 'school' => $school->name]) }}</span>
        </div>
    </div>

    <form wire:submit="save">
        <div class="row g-3">
            <div class="col-md-3 col-lg-2">
                <ul class="nav nav-pills flex-column">
                    @foreach ($modules as $moduleCode)
                        <li class="nav-item">
                            <button
                                type="button"
                                class="nav-link d-flex align-items-center justify-content-between w-100 text-start {{ $activeModule === $moduleCode ? 'active' : '' }}"
                                wire:click="setActiveModule('{{ $moduleCode }}')"
                            >
                                {{ $moduleCode }}
                                <span class="badge {{ $activeModule === $moduleCode ? 'text-bg-light' : 'text-bg-secondary' }} ms-1">{{ $moduleCounts[$moduleCode] ?? 0 }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="col-md-9 col-lg-10">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 3rem;"></th>
                                    <th>{{ __('Permission') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th style="width: 12rem;">{{ __('Scope') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($permissions as $permission)
                                    <tr wire:key="permission-{{ $permission->id }}">
                                        <td>
                                            <div class="form-check form-switch mb-0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="permission-{{ $permission->id }}"
                                                    @checked($granted[$permission->id] ?? false)
                                                    wire:click="toggleGrant({{ $permission->id }})"
                                                >
                                            </div>
                                        </td>
                                        <td>
                                            <label class="fw-medium mb-0" for="permission-{{ $permission->id }}">{{ $permission->name }}</label>
                                            @if ($permission->is_dangerous)
                                                <span class="badge text-bg-danger ms-1">{{ __('Dangerous') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-body-secondary small">{{ $permission->description }}</td>
                                        <td>
                                            @if ($granted[$permission->id] ?? false)
                                                <select class="form-select form-select-sm" wire:model="scopes.{{ $permission->id }}" aria-label="{{ __('Scope for :permission', ['permission' => $permission->name]) }}">
                                                    @foreach ($scopeOptions as $scopeOption)
                                                        <option value="{{ $scopeOption->value }}">{{ ucfirst($scopeOption->value) }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="text-body-secondary">&mdash;</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No permissions registered for this module yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        {{ __('Save') }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
