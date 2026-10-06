<div>
    <h4 class="mb-1">{{ __('Surveys') }}</h4>
    <p class="text-body-secondary small mb-4">{{ __('Open surveys for you. Anonymous surveys never record who answered.') }}</p>

    @if ($survey)
        <div class="card" style="max-width:42rem">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ $survey->title }} @if ($survey->is_anonymous) <span class="badge text-bg-secondary">{{ __('anonymous') }}</span> @endif</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('surveyId', null)">{{ __('Back') }}</button>
            </div>
            <div class="card-body">
                @foreach ($survey->questions as $question)
                    @if (isset($visible[$question->sequence]))
                        <div class="mb-3" wire:key="q-{{ $question->sequence }}">
                            <label class="form-label fw-semibold">{{ $question->sequence }}. {{ $question->prompt }} @if ($question->is_required) <span class="text-danger">*</span> @endif</label>
                            @if ($question->question_type === 'single_choice')
                                @foreach ($question->options ?? [] as $option)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="q{{ $question->sequence }}-{{ $loop->index }}" value="{{ $option }}" wire:model.live="answers.{{ $question->sequence }}">
                                        <label class="form-check-label" for="q{{ $question->sequence }}-{{ $loop->index }}">{{ $option }}</label>
                                    </div>
                                @endforeach
                            @elseif ($question->question_type === 'multi_choice')
                                @foreach ($question->options ?? [] as $option)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="q{{ $question->sequence }}-{{ $loop->index }}" value="{{ $option }}" wire:model="answers.{{ $question->sequence }}">
                                        <label class="form-check-label" for="q{{ $question->sequence }}-{{ $loop->index }}">{{ $option }}</label>
                                    </div>
                                @endforeach
                            @elseif ($question->question_type === 'scale')
                                <select class="form-select w-auto" wire:model.live="answers.{{ $question->sequence }}">
                                    <option value="">{{ __('Choose 1–5') }}</option>
                                    @for ($i = 1; $i <= 5; $i++) <option value="{{ $i }}">{{ $i }}</option> @endfor
                                </select>
                            @elseif ($question->question_type === 'nps')
                                <select class="form-select w-auto" wire:model.live="answers.{{ $question->sequence }}">
                                    <option value="">{{ __('0 = not at all likely, 10 = extremely likely') }}</option>
                                    @for ($i = 0; $i <= 10; $i++) <option value="{{ $i }}">{{ $i }}</option> @endfor
                                </select>
                            @else
                                <textarea class="form-control" rows="3" maxlength="5000" wire:model="answers.{{ $question->sequence }}"></textarea>
                            @endif
                        </div>
                    @endif
                @endforeach
                <button type="button" class="btn btn-primary" wire:click="submit">{{ __('Submit') }}</button>
            </div>
        </div>
    @else
        <div class="list-group" style="max-width:42rem">
            @forelse ($surveys as $row)
                <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" wire:click="open({{ $row->id }})" wire:key="survey-{{ $row->id }}">
                    <span>{{ $row->title }}</span>
                    <span class="text-body-secondary small">{{ $row->questions->count() }} {{ __('questions') }}</span>
                </button>
            @empty
                <div class="list-group-item text-body-secondary">{{ __('No open surveys for you right now.') }}</div>
            @endforelse
        </div>
    @endif
</div>
