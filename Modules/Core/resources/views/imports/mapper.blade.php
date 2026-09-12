<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('imports.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Import: :label', ['label' => $definition->label]) }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Upload a CSV, map its columns, and choose how duplicates are handled.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="createBatch">
                <div class="mb-4">
                    <label class="form-label" for="file">{{ __('CSV file') }}</label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" wire:model.live="file" accept=".csv,text/csv,text/plain">
                    @error('file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div wire:loading wire:target="file" class="form-text">{{ __('Uploading…') }}</div>
                </div>

                @if ($sourceHeaders !== [])
                    <div class="mb-4">
                        <h6 class="mb-2">{{ __('Column mapping') }}</h6>
                        @error('columnMapping') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Template column') }}</th>
                                        <th>{{ __('Uploaded file column') }}</th>
                                        <th>{{ __('Notes') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($templateColumns as $index => $column)
                                        <tr>
                                            <td>
                                                {{ $column->header }}
                                                @if ($column->required)
                                                    <span class="text-danger">*</span>
                                                @endif
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm" wire:model="columnMapping.{{ $index }}">
                                                    <option value="">{{ __('— not mapped —') }}</option>
                                                    @foreach ($sourceHeaders as $sourceHeader)
                                                        <option value="{{ $sourceHeader }}">{{ $sourceHeader }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="small text-body-secondary">{{ $column->notes }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="duplicateStrategy" wire:model="duplicateStrategy">
                                <option value="skip">{{ __('Skip duplicates') }}</option>
                                <option value="update">{{ __('Update existing') }}</option>
                                <option value="create_anyway">{{ __('Create anyway') }}</option>
                            </select>
                            <label for="duplicateStrategy">{{ __('On duplicate') }}</label>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="dryRun" wire:model="dryRun">
                            <label class="form-check-label" for="dryRun">{{ __('Dry run (validate only, write nothing)') }}</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create batch') }}</button>
            </form>
        </div>
    </div>
</div>
