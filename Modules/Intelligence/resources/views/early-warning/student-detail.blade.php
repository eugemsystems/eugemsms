<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('Learner risk detail') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Every factor is a plain-language, individually verifiable fact. This is a prompt to look closer, not a conclusion — it does not diagnose a cause and is never shown to the learner or guardians.') }}</p>
        </div>
    </div>
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('insights.early-warning.queue', $school) }}" class="btn btn-sm btn-outline-secondary" wire:navigate><i class="ri ri-arrow-left-line"></i> {{ __('Queue') }}</a>
        <strong>{{ $student->fullName() }}</strong>
        <select class="form-select form-select-sm w-auto ms-auto" wire:model.live="termId">
            @foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach
        </select>
        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="recompute">{{ __('Recompute') }}</button>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span>{{ __('Contributing factors') }}</span>
                    @if ($score) <span>{{ number_format($score->composite_score, 1) }} · {{ __(ucfirst($score->risk_band)) }}</span> @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Factor') }}</th><th class="text-end">{{ __('Weight') }}</th><th class="text-end">{{ __('Contribution') }}</th><th>{{ __('Source') }}</th></tr></thead>
                        <tbody>
                            @forelse ($score?->contributing_factors ?? [] as $factor)
                                <tr>
                                    <td>{{ $factor['plain_language'] }}</td>
                                    <td class="text-end">{{ $factor['weight'] }}</td>
                                    <td class="text-end">{{ $factor['contribution'] }}</td>
                                    <td class="small text-body-secondary">{{ $factor['source'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No score for this term yet — recompute to generate one.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('History') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Term') }}</th><th>{{ __('Band') }}</th><th class="text-end">{{ __('Score') }}</th></tr></thead>
                        <tbody>
                            @forelse ($history as $row)
                                <tr wire:key="h-{{ $row->id }}"><td>{{ $termNames[$row->term_id] ?? '' }}</td><td>{{ __(ucfirst($row->risk_band)) }}</td><td class="text-end">{{ number_format($row->composite_score, 1) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No history.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
