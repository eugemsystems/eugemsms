<div>
    <h4 class="mb-1">{{ __('Grading scales') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Pure data — adding a scale or changing a boundary needs no deployment.') }}</p>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('Scales') }}</div>
                <div class="accordion accordion-flush" id="scales-accordion">
                    @forelse ($scales as $scale)
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#scale-{{ $scale->id }}">
                                    {{ $scale->code }} — {{ $scale->name }} ({{ $scale->scale_type }}{{ $scale->lower_is_better ? ', lower is better' : '' }})
                                </button>
                            </h2>
                            <div id="scale-{{ $scale->id }}" class="accordion-collapse collapse">
                                <div class="accordion-body p-0">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>{{ __('Grade') }}</th><th>{{ __('Range') }}</th><th>{{ __('Points') }}</th><th>{{ __('Pass') }}</th></tr></thead>
                                        <tbody>
                                            @foreach ($scale->bands->sortBy('sort_order') as $band)
                                                <tr><td>{{ $band->grade }}</td><td>{{ $band->min_percent }}–{{ $band->max_percent }}</td><td>{{ $band->points ?? '—' }}</td><td>{{ $band->is_pass ? __('Yes') : __('No') }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-body-secondary py-4">{{ __('No grading scales yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('New grading scale') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder=" ">
                                    <label>{{ __('Code') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="scaleType">
                                        <option value="letter">{{ __('Letter') }}</option>
                                        <option value="unit">{{ __('Unit') }}</option>
                                        <option value="band">{{ __('Band') }}</option>
                                        <option value="points">{{ __('Points') }}</option>
                                        <option value="percentage">{{ __('Percentage') }}</option>
                                    </select>
                                    <label>{{ __('Scale type') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="lowerIsBetter">
                                    <label class="form-check-label">{{ __('Lower is better') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control form-control-sm" wire:model="passGrade" placeholder="{{ __('Pass grade (optional)') }}">
                            </div>
                        </div>

                        <h6 class="d-flex justify-content-between align-items-center">
                            {{ __('Bands (must span 0–100 contiguously)') }}
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addBand">{{ __('Add band') }}</button>
                        </h6>
                        @foreach ($bands as $index => $band)
                            <div class="row g-1 mb-1 align-items-center">
                                <div class="col-2"><input type="text" class="form-control form-control-sm" wire:model="bands.{{ $index }}.grade" placeholder="{{ __('Grade') }}"></div>
                                <div class="col-3"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="bands.{{ $index }}.minPercent" placeholder="{{ __('Min %') }}"></div>
                                <div class="col-3"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="bands.{{ $index }}.maxPercent" placeholder="{{ __('Max %') }}"></div>
                                <div class="col-2"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="bands.{{ $index }}.points" placeholder="{{ __('Pts') }}"></div>
                                <div class="col-1 form-check">
                                    <input class="form-check-input" type="checkbox" wire:model="bands.{{ $index }}.isPass" title="{{ __('Is pass') }}">
                                </div>
                                <div class="col-1">
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeBand({{ $index }})">&times;</button>
                                </div>
                            </div>
                        @endforeach

                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create scale') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
