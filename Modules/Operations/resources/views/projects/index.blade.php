<div>
    <h4 class="mb-1">{{ __('Capital projects') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Budget, commitment and spend, with capitalisation to FIN-10 on completion where flagged.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Name') }}</th><th>{{ __('Status') }}</th><th>{{ __('Budget') }}</th><th>{{ __('Spent') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($projects as $project)
                                <tr wire:key="project-{{ $project->id }}">
                                    <td>{{ $project->project_number }}</td>
                                    <td>{{ $project->name }}</td>
                                    <td><span class="badge text-bg-secondary">{{ $project->status }}</span></td>
                                    <td>{{ number_format($project->budget_minor / 100, 2) }}</td>
                                    <td>{{ number_format($project->spent_minor / 100, 2) }}</td>
                                    <td>
                                        @if (in_array($project->status, ['planning', 'approved']))
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="advance({{ $project->id }})">{{ __('Advance') }}</button>
                                        @endif
                                        @if (in_array($project->status, ['approved', 'in_progress']))
                                            <button type="button" class="btn btn-sm btn-success" wire:click="complete({{ $project->id }})">{{ __('Complete') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No capital projects.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New project') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <textarea class="form-control mb-2" wire:model="description" rows="2" placeholder="{{ __('Description (optional)') }}"></textarea>
                    <input type="number" class="form-control mb-2" wire:model="budgetMinor" placeholder="{{ __('Budget (minor units)') }}">
                    <select class="form-select mb-2" wire:model="budgetLineId">
                        <option value="">{{ __('Budget line (optional)') }}</option>
                        @foreach ($budgetLines as $line)
                            <option value="{{ $line->id }}">#{{ $line->id }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="startsOn">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="capitaliseOnCompletion" wire:model="capitaliseOnCompletion">
                        <label class="form-check-label" for="capitaliseOnCompletion">{{ __('Capitalise on completion') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create project') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
