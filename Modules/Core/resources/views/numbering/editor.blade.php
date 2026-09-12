<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('numbering.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ $editingSeriesId !== null ? __('Edit numbering series') : __('New numbering series') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Example pattern: :example', ['example' => '{SCHOOL}/{TYPE}/{YEAR}/{SEQ:6}']) }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="row g-3">
                    @if ($editingSeriesId === null)
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('documentType') is-invalid @enderror" id="documentType" wire:model="documentType" placeholder=" ">
                                <label for="documentType">{{ __('Document type') }}</label>
                                @error('documentType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text">{{ __('e.g. receipt, invoice, credit_note, exeat.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" id="academicYearId" wire:model="academicYearId">
                                    <option value="">{{ __('Every academic year') }}</option>
                                    @foreach ($academicYears as $year)
                                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                                    @endforeach
                                </select>
                                <label for="academicYearId">{{ __('Academic year (optional)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" id="termId" wire:model="termId">
                                    <option value="">{{ __('Every term') }}</option>
                                    @foreach ($terms as $term)
                                        <option value="{{ $term->id }}">{{ $term->academicYear->name }} — {{ $term->name }}</option>
                                    @endforeach
                                </select>
                                <label for="termId">{{ __('Term (optional)') }}</label>
                            </div>
                        </div>
                    @else
                        <div class="col-12">
                            <div class="alert alert-secondary mb-0">
                                {{ __('Document type and period are fixed once a series is created.') }}
                                <strong>{{ $documentType }}</strong>
                                @if ($academicYearId || $termId)
                                    — {{ collect($academicYears)->firstWhere('id', $academicYearId)?->name }}
                                    {{ collect($terms)->firstWhere('id', $termId)?->name }}
                                @else
                                    — {{ __('every period') }}
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('pattern') is-invalid @enderror" id="pattern" wire:model="pattern" placeholder=" ">
                            <label for="pattern">{{ __('Pattern') }}</label>
                            @error('pattern') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('prefix') is-invalid @enderror" id="prefix" wire:model="prefix" placeholder=" ">
                            <label for="prefix">{{ __('Prefix (optional)') }}</label>
                            @error('prefix') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="number" min="1" max="10" class="form-control @error('sequencePadding') is-invalid @enderror" id="sequencePadding" wire:model="sequencePadding" placeholder=" ">
                            <label for="sequencePadding">{{ __('Digits') }}</label>
                            @error('sequencePadding') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('resetPolicy') is-invalid @enderror" id="resetPolicy" wire:model="resetPolicy">
                                <option value="never">{{ __('Never') }}</option>
                                <option value="yearly">{{ __('Yearly') }}</option>
                                <option value="termly">{{ __('Termly') }}</option>
                            </select>
                            <label for="resetPolicy">{{ __('Reset policy') }}</label>
                            @error('resetPolicy') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($editingSeriesId !== null)
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="isActive" wire:model="isActive">
                                <label class="form-check-label" for="isActive">{{ __('Active') }}</label>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        {{ $editingSeriesId !== null ? __('Save changes') : __('Create series') }}
                    </button>
                    <a href="{{ route('numbering.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
