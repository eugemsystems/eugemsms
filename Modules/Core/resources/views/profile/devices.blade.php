<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('My devices') }}</h4>
        <p class="text-body-secondary mb-0">
            {{ __('Devices that have signed in to the mobile app or API on your behalf.') }}
        </p>
    </div>

    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="ri ri-information-line fs-5 flex-shrink-0"></i>
        <div>
            <p class="mb-1">
                {{ __('You can hold at most :max active devices at a time. Once that limit is reached, the device you have not used in the longest time is automatically signed out to make room for a new one.', ['max' => $maxDevices]) }}
            </p>
            <p class="mb-0">
                {{ __('To sign out of every device at once, use the button on the') }}
                @if (Route::has('profile.security'))
                    <a href="{{ route('profile.security') }}" wire:navigate>{{ __('Security') }}</a>
                @else
                    {{ __('Security') }}
                @endif
                {{ __('page instead.') }}
            </p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-borderless mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Device') }}</th>
                        <th>{{ __('Platform') }}</th>
                        <th>{{ __('Last used') }}</th>
                        <th>{{ __('Added') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tokens as $token)
                        <tr wire:key="device-{{ $token->id }}">
                            <td>
                                <div class="fw-medium">{{ $token->name }}</div>
                                @if ($token->device_model)
                                    <div class="small text-body-secondary">{{ $token->device_model }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $token->device_platform ? ucfirst($token->device_platform) : __('Unknown') }}
                            </td>
                            <td>
                                @if ($token->last_used_at)
                                    {{ $token->last_used_at->diffForHumans() }}
                                    @if ($token->last_used_ip)
                                        <div class="small text-body-secondary">{{ $token->last_used_ip }}</div>
                                    @endif
                                @else
                                    {{ __('Never') }}
                                @endif
                            </td>
                            <td>{{ $token->created_at?->format('d M Y') }}</td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-icon btn-sm btn-outline-danger"
                                    wire:click="revoke({{ $token->id }})"
                                    wire:confirm="{{ __('Sign out this device?') }}"
                                    title="{{ __('Sign out this device') }}"
                                    aria-label="{{ __('Sign out this device') }}"
                                >
                                    <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">
                                {{ __('No devices are currently signed in.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
