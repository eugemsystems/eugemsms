<div>
    <h4 class="mb-1">{{ __('Bulk allocation run') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Every allocation produced is a draft. Review every soft violation before confirming (BR-BRD-01-007).') }}</p>

    <button type="button" class="btn btn-primary mb-4" wire:click="run" wire:loading.attr="disabled">
        {{ __('Run bulk allocation for all unallocated boarders') }}
    </button>

    @if ($lastOutcomes !== [])
        <div class="card mb-4">
            <div class="card-header">{{ __('This run\'s outcomes') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Result') }}</th><th>{{ __('Detail') }}</th></tr></thead>
                    <tbody>
                        @foreach ($lastOutcomes as $outcome)
                            <tr>
                                <td>{{ $students->get($outcome['studentId'])?->first_name }} {{ $students->get($outcome['studentId'])?->last_name }}</td>
                                <td><span class="badge text-bg-{{ $outcome['placed'] ? 'success' : 'danger' }}">{{ $outcome['placed'] ? __('Placed (draft)') : __('Blocked') }}</span></td>
                                <td>
                                    @if (! $outcome['placed'])
                                        {{ $outcome['blockingReason'] }}
                                    @elseif ($outcome['softViolations'] !== [])
                                        <span class="text-warning">{{ implode(', ', $outcome['softViolations']) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">{{ __('Pending draft allocations (all terms)') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Hostel') }}</th><th>{{ __('Room / bed') }}</th><th>{{ __('Effective from') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($drafts as $draft)
                        <tr wire:key="draft-{{ $draft->id }}">
                            <td>{{ $draft->student->first_name }} {{ $draft->student->last_name }}</td>
                            <td>{{ $draft->hostel->code }}</td>
                            <td>{{ $draft->bed->room->room_number }} / {{ $draft->bed->bed_number }}</td>
                            <td>{{ $draft->effective_from->toDateString() }}</td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-success" wire:click="confirm({{ $draft->id }})">{{ __('Confirm') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No drafts awaiting confirmation.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
