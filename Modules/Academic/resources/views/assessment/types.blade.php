<div>
    <h4 class="mb-1">{{ __('Assessment types') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Topic test, midterm, end-term, SBP, and so on.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Types') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Default weight') }}</th></tr></thead>
                        <tbody>
                            @forelse ($types as $type)
                                <tr wire:key="type-{{ $type->id }}">
                                    <td>{{ $type->code }}</td>
                                    <td>{{ $type->name }}</td>
                                    <td>{{ str_replace('_', ' ', $type->category) }}</td>
                                    <td>{{ $type->default_weight_percent }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No assessment types yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New assessment type') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder=" ">
                                    <label>{{ __('Code') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="category">
                                        <option value="coursework">{{ __('Coursework') }}</option>
                                        <option value="examination">{{ __('Examination') }}</option>
                                        <option value="continuous_assessment">{{ __('Continuous assessment') }}</option>
                                    </select>
                                    <label>{{ __('Category') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" step="0.01" class="form-control @error('defaultWeightPercent') is-invalid @enderror" wire:model="defaultWeightPercent" placeholder=" ">
                                    <label>{{ __('Default weight %') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="isExamination">
                                    <label class="form-check-label">{{ __('Is examination') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" wire:model="appearsOnReportCard">
                                    <label class="form-check-label">{{ __('Appears on report card') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create type') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
