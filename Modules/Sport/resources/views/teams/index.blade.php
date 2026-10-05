<div>
    <h4 class="mb-1">{{ __('Teams') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Activity') }}</th><th>{{ __('Age group') }}</th><th>{{ __('Level') }}</th></tr></thead>
                        <tbody>
                            @forelse ($teams as $team)
                                <tr wire:key="team-{{ $team->id }}">
                                    <td>{{ $team->name }}</td>
                                    <td>{{ $team->activity->name }}</td>
                                    <td>{{ $team->age_group ?? '—' }}</td>
                                    <td>{{ $team->level ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No teams.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New team') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="activityId">
                        <option value="">{{ __('Activity') }}</option>
                        @foreach ($activities as $activity)
                            <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name (e.g. 1st XI)') }}">
                    <input type="text" class="form-control mb-2" wire:model="ageGroup" placeholder="{{ __('Age group (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="level" placeholder="{{ __('Level (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create team') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
