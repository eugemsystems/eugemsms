<div>
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('users.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
                <i class="ri ri-arrow-left-line"></i>
            </a>
            <div>
                <h4 class="mb-1">{{ $user->first_name }} {{ $user->last_name }}</h4>
                <p class="text-body-secondary mb-0">
                    @if ($user->email) {{ $user->email }} @endif
                    @if ($user->phone) &middot; {{ $user->phone }} @endif
                    @if ($user->username) &middot; {{ '@'.$user->username }} @endif
                </p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-primary" wire:navigate>
                <i class="ri ri-edit-line me-1"></i>{{ __('Edit') }}
            </a>
            <button type="button" class="btn btn-outline-secondary" wire:click="resetPassword" wire:confirm="{{ __('Reset this user\'s password? They will be required to set a new one on next login.') }}">
                <i class="ri ri-lock-password-line me-1"></i>{{ __('Reset password') }}
            </button>
            @if ($user->status->value === 'active')
                <button type="button" class="btn btn-outline-warning" wire:click="deactivate" wire:confirm="{{ __('Deactivate this user? They will be immediately signed out everywhere.') }}">
                    <i class="ri ri-forbid-line me-1"></i>{{ __('Deactivate') }}
                </button>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Identity') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('Type') }}</dt>
                        <dd class="col-7 text-capitalize">{{ $user->user_type->value }}</dd>

                        <dt class="col-5">{{ __('Status') }}</dt>
                        <dd class="col-7">
                            <span @class([
                                'badge text-capitalize',
                                'text-bg-success' => $user->status->value === 'active',
                                'text-bg-secondary' => $user->status->value === 'inactive',
                                'text-bg-warning' => $user->status->value === 'suspended',
                                'text-bg-danger' => $user->status->value === 'locked',
                                'text-bg-info' => $user->status->value === 'pending',
                            ])>{{ $user->status->value }}</span>
                        </dd>

                        <dt class="col-5">{{ __('Locale') }}</dt>
                        <dd class="col-7">{{ $user->locale }}</dd>

                        <dt class="col-5">{{ __('Must change password') }}</dt>
                        <dd class="col-7">{{ $user->must_change_password ? __('Yes') : __('No') }}</dd>

                        <dt class="col-5">{{ __('Last login') }}</dt>
                        <dd class="col-7">{{ $user->last_login_at?->diffForHumans() ?? __('Never') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Roles per school') }}</h5></div>
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('School') }}</th>
                                <th>{{ __('Role') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($roleAssignments as $assignment)
                                <tr>
                                    <td>{{ $assignment->school_name ?? __('System-wide') }}</td>
                                    <td>{{ $assignment->role_name }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-body-secondary py-4">{{ __('No roles assigned.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Devices') }}</h5></div>
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Device') }}</th>
                                <th>{{ __('Platform') }}</th>
                                <th>{{ __('Last used') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($devices as $device)
                                <tr wire:key="device-{{ $device->id }}">
                                    <td>{{ $device->name }}</td>
                                    <td>{{ $device->device_platform ? ucfirst($device->device_platform) : __('Unknown') }}</td>
                                    <td>{{ $device->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="revokeToken({{ $device->id }})" wire:confirm="{{ __('Revoke this device?') }}" title="{{ __('Revoke') }}" aria-label="{{ __('Revoke') }}">
                                            <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No active devices.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Recent login history') }}</h5></div>
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('When') }}</th>
                                <th>{{ __('Guard') }}</th>
                                <th>{{ __('Result') }}</th>
                                <th>{{ __('IP address') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($loginHistory as $attempt)
                                <tr>
                                    <td>{{ $attempt->attempted_at->format('d M Y H:i') }}</td>
                                    <td>{{ $attempt->guard }}</td>
                                    <td>
                                        @if ($attempt->was_successful)
                                            <span class="badge text-bg-success">{{ __('Success') }}</span>
                                        @else
                                            <span class="badge text-bg-danger">{{ $attempt->failure_reason ?? __('Failed') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $attempt->ip_address }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No login attempts recorded.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Linked records') }}</h5></div>
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('School') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Active') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($linkedRecords as $link)
                                <tr>
                                    <td>{{ $link->school?->name }}</td>
                                    <td class="text-capitalize">{{ $link->linked_type }}</td>
                                    <td>{{ $link->is_active ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-body-secondary py-4">{{ __('No linked records.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
