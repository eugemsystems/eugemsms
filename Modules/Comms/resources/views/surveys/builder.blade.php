<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Survey builder') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('A survey opens as soon as it is saved and cannot be edited afterwards — check it carefully. An anonymous survey stores no respondent identity at all.') }}</p>
        </div>
        <a href="{{ route('comms.surveys.results', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Results') }}</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Survey') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Responses') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($surveys as $survey)
                                <tr wire:key="sv-{{ $survey->id }}">
                                    <td>{{ $survey->title }} @if ($survey->is_anonymous) <span class="badge bg-label-info">{{ __('anonymous') }}</span> @endif</td>
                                    <td><span class="badge {{ $survey->status === 'open' ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $survey->status }}</span></td>
                                    <td class="text-end">{{ $survey->response_count }}</td>
                                    <td class="text-end">@if ($survey->status === 'open') <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="close({{ $survey->id }})" wire:confirm="{{ __('Close this survey?') }}">{{ __('Close') }}</button> @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No surveys yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">{{ __('New survey') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                    @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <select class="form-select" wire:model="purpose">
                                <option value="satisfaction">{{ __('Satisfaction') }}</option>
                                <option value="feedback">{{ __('Feedback') }}</option>
                                <option value="research">{{ __('Research') }}</option>
                                <option value="exit_interview">{{ __('Exit interview') }}</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select" wire:model="audienceScope">
                                @foreach ($scopeOptions as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="sv-anon" wire:model="isAnonymous"><label class="form-check-label small" for="sv-anon">{{ __('Anonymous — store no respondent identity') }}</label></div>

                    @foreach ($questions as $index => $question)
                        <div class="border rounded p-2 mb-2" wire:key="q-{{ $index }}">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="small">{{ __('Question :n', ['n' => $index + 1]) }}</strong>
                                @if (count($questions) > 1) <button type="button" class="btn btn-xs btn-outline-danger" wire:click="removeQuestion({{ $index }})"><i class="ri ri-close-line"></i></button> @endif
                            </div>
                            <div class="row g-2 mb-1">
                                <div class="col-4">
                                    <select class="form-select form-select-sm" wire:model.live="questions.{{ $index }}.type">
                                        @foreach ($questionTypes as $type) <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="col-8"><input type="text" class="form-control form-control-sm" wire:model="questions.{{ $index }}.prompt" placeholder="{{ __('Question text') }}"></div>
                            </div>
                            @error("questions.{$index}.prompt") <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                            @if (in_array($question['type'], ['single_choice', 'multi_choice'], true))
                                <textarea class="form-control form-control-sm mb-1" rows="3" wire:model="questions.{{ $index }}.options" placeholder="{{ __('One option per line') }}"></textarea>
                                @error("questions.{$index}.options") <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                            @elseif ($question['type'] === 'scale') <div class="small text-body-secondary mb-1">{{ __('Respondents answer on a 1–5 scale.') }}</div>
                            @elseif ($question['type'] === 'nps') <div class="small text-body-secondary mb-1">{{ __('Respondents answer 0–10 (“how likely are you to recommend us?”).') }}</div>
                            @endif
                            <div class="row g-2 align-items-center">
                                <div class="col-auto"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="q-req-{{ $index }}" wire:model="questions.{{ $index }}.required"><label class="form-check-label small" for="q-req-{{ $index }}">{{ __('Required') }}</label></div></div>
                                <div class="col"><input type="text" class="form-control form-control-sm" wire:model="questions.{{ $index }}.skipIf" placeholder="{{ __('Skip rule: if answer is…') }}"></div>
                                <div class="col-3"><input type="number" class="form-control form-control-sm" min="2" wire:model="questions.{{ $index }}.skipTo" placeholder="{{ __('jump to Q#') }}"></div>
                            </div>
                            @error("questions.{$index}.skipTo") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                    @error('questions') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="addQuestion">{{ __('Add question') }}</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="save" wire:confirm="{{ __('Save and open this survey? It cannot be edited afterwards.') }}">{{ __('Save survey') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
