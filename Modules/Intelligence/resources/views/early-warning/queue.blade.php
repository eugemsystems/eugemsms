<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('At-risk review queue') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('An advisory prompt for pastoral staff — not a diagnosis. Nothing here is shown to learners or guardians, and no score contacts anyone.') }}</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div>
                <label class="form-label small mb-1">{{ __('Term') }}</label>
                <select class="form-select form-select-sm" wire:model.live="termId">
                    @foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="form-label small mb-1 d-block">{{ __('Bands') }}</label>
                @foreach ($allBands as $band)
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="band-{{ $band }}" value="{{ $band }}" wire:model.live="bands"><label class="form-check-label small" for="band-{{ $band }}">{{ __(ucfirst($band)) }}</label></div>
                @endforeach
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm ms-auto" wire:click="recomputeTerm" wire:confirm="{{ __('Recompute every active learner for this term from source data?') }}">{{ __('Recompute this term') }}</button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Band') }}</th><th class="text-end">{{ __('Score') }}</th><th>{{ __('Main factor') }}</th><th>{{ __('Computed') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($scores as $score)
                        @php($top = collect($score->contributing_factors)->sortByDesc('contribution')->first())
                        <tr wire:key="score-{{ $score->id }}">
                            <td>{{ $students[$score->student_id]?->fullName() ?? '—' }}</td>
                            <td><span class="badge text-bg-{{ ['critical' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'][$score->risk_band] ?? 'secondary' }}">{{ __(ucfirst($score->risk_band)) }}</span></td>
                            <td class="text-end">{{ number_format($score->composite_score, 1) }}</td>
                            <td class="small">{{ $top['plain_language'] ?? '—' }}</td>
                            <td class="small">{{ $score->computed_at?->toDayDateTimeString() }}</td>
                            <td class="text-end"><a href="{{ route('insights.early-warning.student', [$school, $score->student_id]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Breakdown') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No learners in the selected bands for this term.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Open withdrawal risk flags') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Flagged') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($flags as $flag)
                        <tr wire:key="flag-{{ $flag->id }}">
                            <td>{{ $students[$flag->student_id]?->fullName() ?? '—' }}</td>
                            <td class="small">{{ $flag->flagged_at?->toDayDateTimeString() }}</td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="startReview({{ $flag->id }})">{{ __('Review') }}</button></td>
                        </tr>
                        @if ($reviewingFlagId === $flag->id)
                            <tr wire:key="flag-form-{{ $flag->id }}"><td colspan="3">
                                <div class="row g-2 align-items-start">
                                    <div class="col-md-3">
                                        <select class="form-select form-select-sm" wire:model="reviewStatus">
                                            <option value="intervention_logged">{{ __('Intervention logged') }}</option>
                                            <option value="resolved">{{ __('Resolved') }}</option>
                                            <option value="withdrawn">{{ __('Withdrawn') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <textarea class="form-control form-control-sm" rows="2" wire:model="interventionNote" placeholder="{{ __('What was done, or why no action is needed (required)') }}"></textarea>
                                        @error('interventionNote') <div class="text-danger small">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-3 d-flex gap-2">
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="closeFlag">{{ __('Save') }}</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="cancelReview">{{ __('Cancel') }}</button>
                                    </div>
                                </div>
                            </td></tr>
                        @endif
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No open flags.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
