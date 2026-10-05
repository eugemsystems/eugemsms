<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.surveys.builder', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Survey results') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Aggregated answers only — no respondent is ever shown.') }}</p>
        </div>
    </div>

    <select class="form-select form-select-sm mb-3 w-auto" wire:model.live="surveyId">
        <option value="">{{ __('Choose a survey…') }}</option>
        @foreach ($surveys as $row) <option value="{{ $row->id }}">{{ $row->title }} ({{ $row->response_count }})</option> @endforeach
    </select>

    @if ($survey)
        <p class="small">{{ trans_choice(':count response|:count responses', $survey->response_count, ['count' => $survey->response_count]) }} @if ($survey->is_anonymous) · {{ __('anonymous survey') }} @endif</p>

        @forelse ($summaries as $summary)
            <div class="card mb-3" wire:key="sum-{{ $summary['sequence'] }}">
                <div class="card-header">{{ $summary['sequence'] }}. {{ $summary['prompt'] }} <span class="text-body-secondary small">({{ $summary['answered'] }} {{ __('answered') }})</span></div>
                <div class="card-body small">
                    @if (isset($summary['counts']))
                        @foreach ($summary['counts'] as $option => $count)
                            <div class="d-flex justify-content-between"><span>{{ $option }}</span><strong>{{ $count }}</strong></div>
                        @endforeach
                    @elseif (array_key_exists('average', $summary))
                        {{ __('Average') }}: <strong>{{ $summary['average'] ?? '—' }}</strong> / 5
                    @elseif (array_key_exists('nps', $summary))
                        {{ __('Net promoter score') }}: <strong>{{ $summary['nps'] ?? '—' }}</strong>
                    @else
                        @forelse ($summary['texts'] ?? [] as $text) <div class="border-bottom py-1">{{ $text }}</div>
                        @empty <span class="text-body-secondary">{{ __('No written answers.') }}</span> @endforelse
                    @endif
                </div>
            </div>
        @empty
            <p class="text-body-secondary">{{ __('This survey has no questions.') }}</p>
        @endforelse
    @endif
</div>
