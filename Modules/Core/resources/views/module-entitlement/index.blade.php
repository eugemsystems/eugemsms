<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'modules'])

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Module') }}</th>
                        <th>{{ __('Dependencies') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modules as $module)
                        <tr>
                            <td class="fw-medium">{{ $module['code'] }}</td>
                            <td>
                                @forelse ($module['dependencies'] as $dependency)
                                    <span class="badge text-bg-light border me-1">{{ $dependency }}</span>
                                @empty
                                    <span class="text-body-tertiary small">{{ __('None') }}</span>
                                @endforelse
                            </td>
                            <td>
                                <span class="badge {{ $module['isEnabled'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $module['isEnabled'] ? __('Enabled') : __('Disabled') }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    @if ($module['isEnabled'])
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-warning" wire:click="toggle('{{ $module['code'] }}', false)" title="{{ __('Disable') }}" aria-label="{{ __('Disable') }}">
                                            <i class="icon-base ri ri-forbid-line icon-22px"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-success" wire:click="toggle('{{ $module['code'] }}', true)" title="{{ __('Enable') }}" aria-label="{{ __('Enable') }}">
                                            <i class="icon-base ri ri-check-line icon-22px"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
