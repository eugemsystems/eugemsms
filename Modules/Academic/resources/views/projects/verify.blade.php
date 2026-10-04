<div>
    <h4 class="mb-1">{{ __('Verify projects') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('HOD sign-off. Unverified projects report as outstanding and block academic period close unless waived.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Brief') }}</th><th>{{ __('Mark') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr wire:key="project-{{ $project->id }}">
                            <td>{{ $project->student?->first_name }} {{ $project->student?->last_name }}</td>
                            <td>{{ $project->brief?->title }}</td>
                            <td>{{ $project->moderated_mark ?? $project->raw_mark }}</td>
                            <td><span class="badge text-bg-warning">{{ ucfirst($project->status) }}</span></td>
                            <td><button type="button" class="btn btn-sm btn-success" wire:click="verify({{ $project->id }})">{{ __('Verify') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('Nothing awaiting verification.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
