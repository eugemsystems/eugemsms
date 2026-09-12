<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('My approvals') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Requests waiting on your decision, oldest first.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Requested') }}</th>
                        <th class="text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr wire:key="request-{{ $request->id }}">
                            <td>{{ \Illuminate\Support\Str::headline($request->approvable_type) }}</td>
                            <td>{{ $request->title }}</td>
                            <td>
                                @if ($request->amount_minor !== null)
                                    {{ number_format($request->amount_minor / 100, 2) }} {{ $request->amount_currency }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $request->requested_at->diffForHumans() }}</td>
                            <td class="text-end">
                                <a href="{{ route('approvals.show', [$school, $request]) }}" class="btn btn-sm btn-primary" wire:navigate>
                                    {{ __('Review') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('Nothing waiting on you right now.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
