<div>
    @php
        $currentColumn = match ($attribute) {
            'grade_level' => 'grade_level_id',
            'class' => 'class_id',
            'section' => 'section_id',
            default => $attribute,
        };
        $currentValue = $student->getAttribute($currentColumn);
    @endphp

    <h4 class="mb-1">{{ __('Change billing attribute') }}</h4>
    <p class="text-body-secondary mb-4">{{ __(':name — currently :value', ['name' => $student->fullName(), 'value' => $currentValue ?? '—']) }}</p>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" wire:model.live="attribute">
                            <option value="enrolment_type">{{ __('Enrolment type') }}</option>
                            <option value="residency">{{ __('Residency') }}</option>
                            <option value="grade_level">{{ __('Grade level') }}</option>
                            <option value="class">{{ __('Class') }}</option>
                            <option value="section">{{ __('Section') }}</option>
                            <option value="pathway">{{ __('Pathway') }}</option>
                        </select>
                        <label>{{ __('Attribute') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    @if ($attribute === 'enrolment_type')
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('newValue') is-invalid @enderror" wire:model.live="newValue">
                                <option value="">{{ __('Select') }}</option>
                                <option value="FULL_TIME">{{ __('Full time') }}</option>
                                <option value="PART_TIME">{{ __('Part time') }}</option>
                            </select>
                            <label>{{ __('New value') }}</label>
                        </div>
                    @elseif ($attribute === 'residency')
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('newValue') is-invalid @enderror" wire:model.live="newValue">
                                <option value="">{{ __('Select') }}</option>
                                <option value="DAY">{{ __('Day') }}</option>
                                <option value="BOARDER">{{ __('Boarder') }}</option>
                                <option value="WEEKLY_BOARDER">{{ __('Weekly boarder') }}</option>
                            </select>
                            <label>{{ __('New value') }}</label>
                        </div>
                    @elseif ($attribute === 'grade_level')
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('newValue') is-invalid @enderror" wire:model.live="newValue">
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($gradeLevels as $gradeLevel)
                                    <option value="{{ $gradeLevel->id }}">{{ $gradeLevel->name }}</option>
                                @endforeach
                            </select>
                            <label>{{ __('New value') }}</label>
                        </div>
                    @elseif ($attribute === 'class')
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('newValue') is-invalid @enderror" wire:model.live="newValue">
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                            <label>{{ __('New value') }}</label>
                        </div>
                    @elseif ($attribute === 'section')
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('newValue') is-invalid @enderror" wire:model.live="newValue">
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                                @endforeach
                            </select>
                            <label>{{ __('New value') }}</label>
                        </div>
                    @else
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('newValue') is-invalid @enderror" wire:model.live="newValue" placeholder=" ">
                            <label>{{ __('New value') }}</label>
                        </div>
                    @endif
                    @error('newValue') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="date" class="form-control @error('effectiveFrom') is-invalid @enderror" wire:model="effectiveFrom">
                        <label>{{ __('Effective from') }}</label>
                        @error('effectiveFrom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-floating form-floating-outline">
                        <textarea class="form-control" wire:model="reason" style="height: 80px" placeholder=" "></textarea>
                        <label>{{ __('Reason (optional)') }}</label>
                    </div>
                </div>
            </div>

            @if (in_array($attribute, ['enrolment_type', 'residency', 'grade_level', 'section', 'pathway']))
                <button type="button" class="btn btn-outline-primary mt-3" wire:click="previewImpact" wire:loading.attr="disabled">
                    <i class="ri ri-calculator-line me-1"></i>{{ __('Preview fee impact') }}
                </button>

                @if ($preview)
                    <div class="alert alert-info mt-3 mb-0">
                        @if ($preview['hasComparableFigures'])
                            @php $delta = $preview['deltaMinor']; @endphp
                            <strong>
                                {{ __('Current: :currency :current → Proposed: :currency :proposed', [
                                    'currency' => $preview['currentCurrency'],
                                    'current' => number_format($preview['currentAmountMinor'] / 100, 2),
                                    'proposed' => number_format($preview['proposedAmountMinor'] / 100, 2),
                                ]) }}
                            </strong>
                            <div>
                                @if ($delta > 0)
                                    {{ __('This will charge an additional :currency :amount.', ['currency' => $preview['proposedCurrency'], 'amount' => number_format($delta / 100, 2)]) }}
                                @elseif ($delta < 0)
                                    {{ __('This will credit :currency :amount.', ['currency' => $preview['proposedCurrency'], 'amount' => number_format(abs($delta) / 100, 2)]) }}
                                @else
                                    {{ __('No change in fee amount.') }}
                                @endif
                            </div>
                        @else
                            {{ __('No comparable fee structure could be resolved for one or both states — proceed with care.') }}
                        @endif
                    </div>
                @endif
            @endif

            <div class="mt-4 d-flex gap-2">
                <a href="{{ route('people.students.show', [$school, $student]) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:confirm="{{ __('Confirm this billing attribute change?') }}">
                    {{ __('Confirm change') }}
                </button>
            </div>
        </div>
    </div>
</div>
