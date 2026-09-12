<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Approval chains') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Routing rules for :school\'s approval requests.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('approvals.chains.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New chain') }}
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Steps') }}</th>
                        <th>{{ __('Priority') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($chains as $chain)
                        <tr wire:key="chain-{{ $chain->id }}">
                            <td>{{ \Illuminate\Support\Str::headline($chain->approvable_type) }}</td>
                            <td>
                                {{ $chain->name }}
                                @if ($chain->is_default)
                                    <span class="badge text-bg-info ms-1">{{ __('Default') }}</span>
                                @endif
                            </td>
                            <td>{{ $chain->steps_count }}</td>
                            <td>{{ $chain->priority }}</td>
                            <td>
                                @if ($chain->is_active)
                                    <span class="badge text-bg-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No approval chains configured yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
