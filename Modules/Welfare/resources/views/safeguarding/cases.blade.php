<div>
    <h4 class="mb-1">{{ __('Safeguarding cases') }}</h4>
    <p class="text-body-secondary mb-4">
        @if ($isLead)
            {{ __('You are the safeguarding lead — every case for this school.') }}
        @else
            {{ __('Showing only cases you hold an active grant for.') }}
        @endif
    </p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Student') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($cases as $case)
                        <tr wire:key="case-{{ $case->id }}">
                            <td>{{ $case->case_reference }}</td>
                            <td>{{ $case->student?->first_name }} {{ $case->student?->last_name }}</td>
                            <td><span class="badge text-bg-{{ $case->status === 'closed' ? 'secondary' : 'warning' }}">{{ $case->status }}</span></td>
                            <td><a href="{{ route('welfare.safeguarding.case', [$school, $case]) }}" class="btn btn-sm btn-outline-primary" wire:navigate>{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No cases visible to you.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
