<div>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('Teacher dashboard') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Syllabus coverage, lesson-plan submission and observation ratings. Facts to start a conversation, not a score.') }}</p>
        </div>
        <select class="form-select form-select-sm w-auto" wire:model.live="termId">@foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach</select>
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>{{ __('Teacher') }}</th><th class="text-end">{{ __('Coverage') }}</th><th class="text-end">{{ __('Plans submitted') }}</th><th class="text-end">{{ __('Plans due') }}</th><th class="text-end">{{ __('Observations') }}</th><th>{{ __('Ratings') }}</th></tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr wire:key="d-{{ $row['staff']->id }}">
                    <td>{{ $row['staff']->fullName() }}</td>
                    <td class="text-end">{{ number_format($row['summary']->coveragePercent, 0) }}%</td>
                    <td class="text-end">{{ number_format($row['summary']->lessonPlanSubmissionRate, 0) }}%</td>
                    <td class="text-end">{{ $row['summary']->lessonPlanCount }}</td>
                    <td class="text-end">{{ $row['summary']->observationCount }}</td>
                    <td class="small">@foreach ($row['summary']->observationRatingCounts as $rating => $count) {{ str_replace('_', ' ', $rating) }} ×{{ $count }}@if (! $loop->last), @endif @endforeach</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No teachers in your scope.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div></div>
</div>
