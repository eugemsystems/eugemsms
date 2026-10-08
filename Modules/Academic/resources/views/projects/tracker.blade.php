<div>
    <h4 class="mb-1">{{ __('Project progress tracker') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Milestone status at a glance — anything not yet submitted is a chase.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-5">
            <select class="form-select" wire:model.live="briefId">
                <option value="">{{ __('Select brief') }}</option>
                @foreach ($briefs as $brief)
                    <option value="{{ $brief->id }}">{{ $brief->title }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($briefId !== null)
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Status') }}</th><th>{{ __('Milestones submitted') }}</th><th>{{ __('Action') }}</th></tr></thead>
                    <tbody>
                        @forelse ($learnerProjects as $project)
                            <tr wire:key="project-{{ $project->id }}">
                                <td>{{ $project->student?->first_name }} {{ $project->student?->last_name }}</td>
                                <td><span class="badge text-bg-{{ in_array($project->status, ['verified', 'moderated'], true) ? 'success' : ($project->status === 'exempt' ? 'secondary' : 'warning') }}">{{ ucfirst($project->status) }}</span></td>
                                <td>{{ $project->milestoneSubmissions->where('status', 'submitted')->count() + $project->milestoneSubmissions->where('status', 'accepted')->count() }} / {{ $project->milestoneSubmissions->count() }}</td>
                                <td>
                                    @if (! in_array($project->status, ['exempt', 'verified'], true))
                                        <input type="text" class="form-control form-control-sm d-inline-block mb-1" style="width: 200px" wire:model="exemptionReasons.{{ $project->id }}" placeholder="{{ __('Exemption reason') }}">
                                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="exempt({{ $project->id }})">{{ __('Exempt') }}</button>
                                    @endif
                                    <a href="{{ route('academic.projects.portfolio', ['school' => $school, 'learnerProject' => $project->id]) }}" class="small" wire:navigate>{{ __('Portfolio') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No learner projects for this brief yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
