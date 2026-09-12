<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('roles.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Permission explorer') }}</h4>
            <span class="text-body-secondary small">{{ __('Who can do X? — reverse lookup for :school', ['school' => $school->name]) }}</span>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <button type="button" class="nav-link {{ $mode === 'permission' ? 'active' : '' }}" wire:click="selectMode('permission')">
                {{ __('By permission') }}
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $mode === 'user' ? 'active' : '' }}" wire:click="selectMode('user')">
                {{ __('By user') }}
            </button>
        </li>
    </ul>

    @if ($mode === 'permission')
        <div class="row g-3">
            <div class="col-md-5 col-lg-4">
                <label class="form-label" for="permission-picker">{{ __('Permission') }}</label>
                <select id="permission-picker" class="form-select" wire:model.live="selectedPermissionId">
                    <option value="">{{ __('— choose a permission —') }}</option>
                    @foreach ($permissions as $permission)
                        <option value="{{ $permission->id }}">{{ $permission->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="card mt-3">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Scope') }}</th>
                            <th>{{ __('Users holding this role in :school', ['school' => $school->name]) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grants as $grant)
                            <tr wire:key="grant-{{ $grant['role']->id }}">
                                <td>
                                    <div class="fw-medium">{{ $grant['role']->display_name }}</div>
                                    <div class="small text-body-secondary">{{ $grant['role']->name }}</div>
                                </td>
                                <td>
                                    @if ($grant['role']->is_system)
                                        <span class="badge text-bg-info">{{ __('System template') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('School role') }}</span>
                                    @endif
                                </td>
                                <td><span class="badge text-bg-light">{{ ucfirst($grant['scope']->value) }}</span></td>
                                <td>{{ $grant['holders'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-body-secondary py-4">
                                    {{ $selectedPermissionId === null ? __('Choose a permission above to see who can do it.') : __('No role in this school grants this permission.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="row g-3">
            <div class="col-md-5 col-lg-4">
                <label class="form-label" for="user-picker">{{ __('User') }}</label>
                <select id="user-picker" class="form-select" wire:model.live="selectedUserId">
                    <option value="">{{ __('— choose a user —') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="card mt-3">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Permission') }}</th>
                            <th>{{ __('Module') }}</th>
                            <th>{{ __('Effective scope') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($userPermissions as $row)
                            <tr wire:key="user-permission-{{ $row['permission']->id }}">
                                <td>{{ $row['permission']->name }}</td>
                                <td>{{ $row['permission']->module_code }}</td>
                                <td><span class="badge text-bg-light">{{ ucfirst($row['scope']->value) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-body-secondary py-4">
                                    {{ $selectedUserId === null ? __('Choose a user above to see everything they can do.') : __('This user holds no permissions in this school.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
