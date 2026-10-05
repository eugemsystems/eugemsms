<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('Enrolment forecast') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('A simple carry-forward of the last completed year, not a statistical model. Thin history is marked low-confidence, never hidden.') }}</p>
        </div>
    </div>
    <div class="d-flex gap-2 mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="academicYearId">
            @foreach ($years as $year) <option value="{{ $year->id }}">{{ $year->name }}</option> @endforeach
        </select>
        @if ($canGenerate) <button type="button" class="btn btn-outline-primary btn-sm" wire:click="generate">{{ __('Generate forecast') }}</button> @endif
    </div>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Grade') }}</th><th class="text-end">{{ __('Intake') }}</th><th class="text-end">{{ __('Attrition') }}</th><th>{{ __('Confidence') }}</th><th>{{ __('Basis') }}</th></tr></thead>
                        <tbody>
                            @forelse ($forecasts as $forecast)
                                <tr wire:key="ef-{{ $forecast->id }}">
                                    <td>{{ $gradeNames[$forecast->grade_level_id] ?? '' }}</td>
                                    <td class="text-end">{{ $forecast->projected_intake ?? '—' }}</td>
                                    <td class="text-end">{{ $forecast->projected_attrition ?? '—' }}</td>
                                    <td><span class="badge text-bg-{{ ['low' => 'warning', 'medium' => 'info', 'high' => 'success'][$forecast->confidence_band] ?? 'secondary' }}">{{ __(ucfirst($forecast->confidence_band)) }}</span></td>
                                    <td class="small text-body-secondary">{{ $forecast->basis_note }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No forecast for this year yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('Capacity planning') }}</div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Projected intake') }}</span><strong>{{ $capacity['projected_total_intake'] ?? 0 }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Hostel capacity') }}</span><strong>{{ $capacity['hostel_capacity'] ?? 0 }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Venue capacity') }}</span><strong>{{ $capacity['venue_capacity'] ?? 0 }}</strong></li>
                </ul>
            </div>
        </div>
    </div>
</div>
