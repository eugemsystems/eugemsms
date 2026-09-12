<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('My requests') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every approval request you have raised.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Requested') }}</th>
                        <th class="text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr wire:key="my-request-{{ $request->id }}">
                            <td>{{ \Illuminate\Support\Str::headline($request->approvable_type) }}</td>
                            <td>{{ $request->title }}</td>
                            <td>
                                <span class="badge text-bg-{{ match ($request->status) { 'approved' => 'success', 'rejected' => 'danger', 'returned' => 'warning', 'cancelled' => 'secondary', default => 'info' } }}">
                                    {{ \Illuminate\Support\Str::headline($request->status) }}
                                </span>
                            </td>
                            <td>{{ $request->requested_at->diffForHumans() }}</td>
                            <td class="text-end">
                                <a href="{{ route('approvals.show', [$school, $request]) }}" class="btn btn-icon btn-sm btn-outline-secondary" wire:navigate title="{{ __('View') }}" aria-label="{{ __('View') }}">
                                    <i class="icon-base ri ri-eye-line icon-22px"></i>
                                </a>
                                @if ($request->status === 'pending')
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="cancel({{ $request->id }})" wire:confirm="{{ __('Cancel this request?') }}" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}">
                                        <i class="icon-base ri ri-close-line icon-22px"></i>
                                    </button>
                                @elseif ($request->status === 'returned')
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-success" wire:click="resubmit({{ $request->id }})" title="{{ __('Resubmit') }}" aria-label="{{ __('Resubmit') }}">
                                        <i class="icon-base ri ri-refresh-line icon-22px"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('You have not raised any requests yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="card-footer">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
