<div>
    <h4 class="mb-1">{{ __('Assessment instruments') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('The abstract continuous-assessment instrument — SBP is the current concrete implementation, CALA is preserved read-only.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Instruments') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Framework') }}</th><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Projects/subject/year') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($instruments as $instrument)
                                <tr wire:key="instrument-{{ $instrument->id }}">
                                    <td>{{ $instrument->framework?->name }}</td>
                                    <td>{{ $instrument->code }}</td>
                                    <td>{{ $instrument->name }}</td>
                                    <td>{{ $instrument->projects_per_subject_per_year }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $instrument->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($instrument->status) }}</span>
                                        @if ($instrument->is_readonly)<span class="badge text-bg-warning">{{ __('Read-only') }}</span>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No instruments yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New instrument') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('frameworkId') is-invalid @enderror" wire:model="frameworkId">
                                        <option value="">{{ __('Select framework') }}</option>
                                        @foreach ($frameworks as $framework)
                                            <option value="{{ $framework->id }}">{{ $framework->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Curriculum framework') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder=" ">
                                    <label>{{ __('Code (e.g. SBP)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="projectsPerSubjectPerYear" min="1">
                                    <label>{{ __('Projects/subject/year') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" step="0.01" class="form-control" wire:model="defaultWeightPercent">
                                    <label>{{ __('Default weight % (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="referenceCircular" placeholder=" ">
                                    <label>{{ __('Reference circular (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12 form-check">
                                <input type="checkbox" class="form-check-input" wire:model="isReadonly" id="isReadonly">
                                <label class="form-check-label" for="isReadonly">{{ __('Read-only (legacy instrument, e.g. CALA)') }}</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create instrument') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
