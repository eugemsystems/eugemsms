<div>
    <h4 class="mb-1">{{ __('Equipment') }}</h4>

    <button type="button" class="btn btn-outline-warning btn-sm mb-3" wire:click="checkOverdue">{{ __('Check overdue equipment') }}</button>
    @if ($overdueChecked)
        <div class="alert {{ $overdueCount > 0 ? 'alert-warning' : 'alert-success' }} py-2">{{ __('Overdue items:') }} {{ $overdueCount }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Student') }}</th><th>{{ __('Activity') }}</th><th>{{ __('Due back') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($issues as $issue)
                                <tr wire:key="issue-{{ $issue->id }}">
                                    <td>{{ $issue->asset->name }}</td>
                                    <td>{{ $issue->student->fullName() }}</td>
                                    <td>{{ $issue->activity->name }}</td>
                                    <td>{{ $issue->expected_return_on?->toDateString() ?? '—' }}</td>
                                    <td>
                                        @if ($issue->returned_at === null)
                                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="returnItem({{ $issue->id }})">{{ __('Return') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No equipment issued.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Issue equipment') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="activityId">
                        <option value="">{{ __('Activity') }}</option>
                        @foreach ($activities as $activity)
                            <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="assetId">
                        <option value="">{{ __('Asset') }}</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->fullName() }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="expectedReturnOn" placeholder="{{ __('Expected return (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="notes" placeholder="{{ __('Notes (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="issue">{{ __('Issue') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
