<div>
    <h4 class="mb-1">{{ __('Moderate projects') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A moderated mark supersedes the marker\'s mark for computation — both remain visible.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Brief') }}</th><th>{{ __('Marker\'s mark') }}</th><th>{{ __('Moderated mark') }}</th><th>{{ __('Note') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr wire:key="project-{{ $project->id }}">
                            <td>{{ $project->student?->first_name }} {{ $project->student?->last_name }}</td>
                            <td>{{ $project->brief?->title }}</td>
                            <td>{{ $project->raw_mark }} / {{ $project->brief?->max_mark }}</td>
                            <td style="width: 120px"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="moderatedMarks.{{ $project->id }}"></td>
                            <td><input type="text" class="form-control form-control-sm" wire:model="notes.{{ $project->id }}" placeholder="{{ __('Moderation note') }}"></td>
                            <td><button type="button" class="btn btn-sm btn-primary" wire:click="moderate({{ $project->id }})">{{ __('Moderate') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('Nothing to moderate.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
