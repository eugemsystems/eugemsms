<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.fees.structures', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ $revisingStructureId !== null ? __('Revise fee structure') : __('New fee structure') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('A revision always creates a new draft version — the current one stays exactly as it was.') }}</p>
        </div>
    </div>

    <form wire:submit="save">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">{{ __('Details') }}</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model="name" placeholder=" ">
                            <label for="name">{{ __('Name') }}</label>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="number" min="1" class="form-control @error('priority') is-invalid @enderror" id="priority" wire:model="priority" placeholder=" ">
                            <label for="priority">{{ __('Priority') }}</label>
                        </div>
                    </div>
                    @if ($revisingStructureId === null)
                        <div class="col-md-2">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('academicYearId') is-invalid @enderror" id="academicYearId" wire:model.live="academicYearId">
                                    @foreach ($academicYears as $year)
                                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                                    @endforeach
                                </select>
                                <label for="academicYearId">{{ __('Academic year') }}</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" id="termId" wire:model="termId">
                                    <option value="">{{ __('All terms') }}</option>
                                    @foreach ($terms as $term)
                                        <option value="{{ $term->id }}">{{ $term->name }}</option>
                                    @endforeach
                                </select>
                                <label for="termId">{{ __('Term') }}</label>
                            </div>
                        </div>
                    @else
                        <div class="col-md-4">
                            <div class="alert alert-secondary mb-0">
                                {{ __('Year/term are fixed once a structure family is created.') }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ __('Rules — who this applies to') }}</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addRule">
                    <i class="ri ri-add-line me-1"></i>{{ __('Add rule') }}
                </button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Attribute') }}</th>
                            <th>{{ __('Operator') }}</th>
                            <th>{{ __('Value') }}</th>
                            <th style="width: 3rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rules as $index => $rule)
                            <tr wire:key="rule-{{ $index }}">
                                <td>
                                    <select class="form-select form-select-sm" wire:model="rules.{{ $index }}.attribute">
                                        @foreach (['section', 'grade_level', 'class', 'enrolment_type', 'residency', 'pathway', 'house', 'nationality', 'gender', 'entry_cohort', 'subject_count'] as $attribute)
                                            <option value="{{ $attribute }}">{{ \Illuminate\Support\Str::headline($attribute) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" wire:model="rules.{{ $index }}.operator">
                                        <option value="equals">{{ __('Equals') }}</option>
                                        <option value="in">{{ __('In') }}</option>
                                        <option value="not_in">{{ __('Not in') }}</option>
                                        <option value="exists">{{ __('Exists') }}</option>
                                        <option value="between">{{ __('Between') }}</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" wire:model="rules.{{ $index }}.value" placeholder="{{ __('e.g. FULL_TIME, or comma-separated for In/Between') }}">
                                </td>
                                <td>
                                    @if (count($rules) > 1)
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeRule({{ $index }})">
                                            <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="previewMatchCount">
                    <i class="ri ri-group-line me-1"></i>{{ __('Preview match count') }}
                </button>
                @if ($matchCount !== null)
                    <span class="ms-2 badge text-bg-info fs-6">{{ __(':count learner(s) match', ['count' => $matchCount]) }}</span>
                    @if ($matchSample !== [])
                        <div class="small text-body-secondary mt-2">
                            {{ __('Sample') }}: {{ collect($matchSample)->pluck('name')->join(', ') }}
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ __('Items — what is charged') }}</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addItem">
                    <i class="ri ri-add-line me-1"></i>{{ __('Add item') }}
                </button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width: 12rem;">{{ __('Component') }}</th>
                            <th style="min-width: 10rem;">{{ __('Basis') }}</th>
                            <th style="width: 6rem;">{{ __('Amount') }}</th>
                            <th style="width: 6rem;">{{ __('Unit rate') }}</th>
                            <th style="width: 5rem;">{{ __('Currency') }}</th>
                            <th style="width: 3rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $item)
                            <tr wire:key="item-{{ $index }}">
                                <td>
                                    <select class="form-select form-select-sm" wire:model="items.{{ $index }}.component_id">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($components as $component)
                                            <option value="{{ $component->id }}">{{ $component->code }} — {{ $component->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" wire:model="items.{{ $index }}.billing_basis">
                                        @foreach (['flat_per_term', 'per_subject', 'per_month', 'per_day', 'per_unit', 'one_off', 'tiered', 'usage_based'] as $basis)
                                            <option value="{{ $basis }}">{{ \Illuminate\Support\Str::headline($basis) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" inputmode="decimal" class="form-control form-control-sm" wire:model="items.{{ $index }}.amount_minor" placeholder="0.00">
                                </td>
                                <td>
                                    <input type="text" inputmode="decimal" class="form-control form-control-sm" wire:model="items.{{ $index }}.unit_rate_minor" placeholder="0.00">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" wire:model="items.{{ $index }}.currency">
                                        <option value="USD">USD</option>
                                        <option value="ZWG">ZWG</option>
                                    </select>
                                </td>
                                <td>
                                    @if (count($items) > 1)
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeItem({{ $index }})">
                                            <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top small text-body-secondary">
                {{ __('Use Amount for flat_per_term/one_off; Unit rate for per_subject/per_month/per_day/per_unit. Tier bands and per-subject rate maps aren\'t edited here yet — set those directly on the record for now.') }}
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                {{ $revisingStructureId !== null ? __('Save new version') : __('Create structure') }}
            </button>
            <a href="{{ route('finance.fees.structures', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
