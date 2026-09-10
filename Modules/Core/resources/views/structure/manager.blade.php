<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'structure'])

    @unless ($currentYear)
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
            <i class="ri ri-error-warning-line"></i>
            {{ __('This school has no current academic year yet, so classes cannot be created until one exists.') }}
        </div>
    @endunless

    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary" wire:click="$set('showSectionModal', true)">
            <i class="ri ri-add-line me-1"></i>{{ __('New section') }}
        </button>
    </div>

    @forelse ($sections as $section)
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <span class="fw-medium">{{ $section->name }}</span>
                    <span class="text-body-secondary small ms-1">{{ $section->code }} &middot; {{ __(ucfirst($section->type)) }}</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openGradeLevelModal({{ $section->id }})">
                    <i class="ri ri-add-line me-1"></i>{{ __('Add grade level') }}
                </button>
            </div>

            @if ($section->gradeLevels->isNotEmpty())
                <div class="list-group list-group-flush">
                    @foreach ($section->gradeLevels as $gradeLevel)
                        <div class="list-group-item">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fw-medium">{{ $gradeLevel->name }}</span>
                                    <span class="text-body-secondary small ms-1">{{ $gradeLevel->code }} &middot; {{ __('ordinal :n', ['n' => $gradeLevel->ordinal]) }}</span>
                                    @if ($gradeLevel->is_exam_level)
                                        <span class="badge text-bg-warning ms-1">{{ __('Exam level') }}</span>
                                    @endif
                                </div>
                                @if ($currentYear)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openClassModal({{ $gradeLevel->id }})">
                                        <i class="ri ri-add-line me-1"></i>{{ __('Add class') }}
                                    </button>
                                @endif
                            </div>

                            @php $classes = $classesByGradeLevel->get($gradeLevel->id, collect()); @endphp
                            @if ($classes->isNotEmpty())
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach ($classes as $class)
                                        <span class="badge text-bg-light border">
                                            {{ $class->name }} &middot; {{ $class->capacity }} {{ __('seats') }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="card-body text-body-secondary small">{{ __('No grade levels yet.') }}</div>
            @endif
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center text-body-secondary py-4">{{ __('No sections yet — add one to get started.') }}</div>
        </div>
    @endforelse

    @if ($showSectionModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="createSection">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New section') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showSectionModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('sectionCode') is-invalid @enderror" id="sectionCode" wire:model="sectionCode" placeholder=" ">
                                <label for="sectionCode">{{ __('Code') }}</label>
                                @error('sectionCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('sectionName') is-invalid @enderror" id="sectionName" wire:model="sectionName" placeholder=" ">
                                <label for="sectionName">{{ __('Name') }}</label>
                                @error('sectionName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" id="sectionType" wire:model="sectionType">
                                    <option value="ecd">{{ __('ECD') }}</option>
                                    <option value="primary">{{ __('Primary') }}</option>
                                    <option value="secondary">{{ __('Secondary') }}</option>
                                    <option value="sixth_form">{{ __('Sixth form') }}</option>
                                </select>
                                <label for="sectionType">{{ __('Type') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showSectionModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showGradeLevelModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="createGradeLevel">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New grade level') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showGradeLevelModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('gradeLevelCode') is-invalid @enderror" id="gradeLevelCode" wire:model="gradeLevelCode" placeholder=" ">
                                <label for="gradeLevelCode">{{ __('Code (e.g. G3)') }}</label>
                                @error('gradeLevelCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('gradeLevelName') is-invalid @enderror" id="gradeLevelName" wire:model="gradeLevelName" placeholder=" ">
                                <label for="gradeLevelName">{{ __('Name (e.g. Grade 3)') }}</label>
                                @error('gradeLevelName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline">
                                <input type="number" class="form-control @error('gradeLevelOrdinal') is-invalid @enderror" id="gradeLevelOrdinal" wire:model="gradeLevelOrdinal" placeholder=" ">
                                <label for="gradeLevelOrdinal">{{ __('Ordinal (promotion order, 0-13)') }}</label>
                                @error('gradeLevelOrdinal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showGradeLevelModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showClassModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="createClass">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New class') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showClassModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('classCode') is-invalid @enderror" id="classCode" wire:model="classCode" placeholder=" ">
                                <label for="classCode">{{ __('Code (e.g. F3B)') }}</label>
                                @error('classCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('className') is-invalid @enderror" id="className" wire:model="className" placeholder=" ">
                                <label for="className">{{ __('Name (e.g. Form 3 Blue)') }}</label>
                                @error('className') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline">
                                <input type="number" class="form-control @error('classCapacity') is-invalid @enderror" id="classCapacity" wire:model="classCapacity" placeholder=" ">
                                <label for="classCapacity">{{ __('Capacity') }}</label>
                                @error('classCapacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showClassModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
