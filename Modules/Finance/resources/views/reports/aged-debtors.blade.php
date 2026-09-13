<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Aged debtors') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Every still-owing invoice, bucketed by days past due.') }}</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" id="currency" wire:model.live="currency">
                            <option value="USD">USD</option>
                            <option value="ZWG">ZWG</option>
                        </select>
                        <label for="currency">{{ __('Currency') }}</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" id="sectionId" wire:model.live="sectionId">
                            <option value="">{{ __('Any section') }}</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}">{{ $section->name }}</option>
                            @endforeach
                        </select>
                        <label for="sectionId">{{ __('Section') }}</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" id="gradeLevelId" wire:model.live="gradeLevelId">
                            <option value="">{{ __('Any grade level') }}</option>
                            @foreach ($gradeLevels as $gradeLevel)
                                <option value="{{ $gradeLevel->id }}">{{ $gradeLevel->name }}</option>
                            @endforeach
                        </select>
                        <label for="gradeLevelId">{{ __('Grade level') }}</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" id="residency" wire:model.live="residency">
                            <option value="">{{ __('Any residency') }}</option>
                            <option value="DAY">{{ __('Day') }}</option>
                            <option value="BOARDER">{{ __('Boarder') }}</option>
                        </select>
                        <label for="residency">{{ __('Residency') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Learner') }}</th>
                        @if ($rows !== [])
                            @foreach (array_keys($rows[0]->bucketMinor) as $bucket)
                                <th class="text-end">{{ $bucket }}</th>
                            @endforeach
                        @endif
                        <th class="text-end">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="debtor-{{ $row->studentId }}">
                            <td>
                                <a href="{{ route('finance.accounts.learner-account', ['school' => $school, 'student' => $row->studentId]) }}" wire:navigate>
                                    {{ $row->admissionNumber }} — {{ $row->studentName }}
                                </a>
                            </td>
                            @foreach ($row->bucketMinor as $amount)
                                <td class="text-end">{{ $amount > 0 ? number_format($amount / 100, 2) : '—' }}</td>
                            @endforeach
                            <td class="text-end fw-semibold">{{ number_format($row->totalMinor / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-body-secondary py-4">{{ __('No outstanding debtors.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
