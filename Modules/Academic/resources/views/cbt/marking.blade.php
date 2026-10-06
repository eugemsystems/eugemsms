<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Manual marking') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Written answers awaiting a mark. Objective answers were marked automatically on submission and do not appear here.') }}</p>
    </div>
    <div class="mb-3"><select class="form-select form-select-sm w-auto" wire:model.live="testId">@foreach ($tests as $option) <option value="{{ $option->id }}">{{ $option->title }} ({{ $option->status }})</option> @endforeach</select></div>
    @forelse ($responses as $response)
        @php($question = $questions->get($response->question_id))
        <div class="card mb-3" wire:key="r-{{ $response->id }}"><div class="card-body small">
            <div class="d-flex justify-content-between"><strong>{{ $students->get($attempts->get($response->attempt_id)?->student_id)?->fullName() ?? '—' }}</strong><span>{{ $response->mark_awarded !== null ? $response->mark_awarded.' / '.$question?->max_mark : __('Unmarked') }}</span></div>
            <div class="text-body-secondary mt-1">{{ $question?->prompt }}</div>
            <div class="border rounded p-2 my-2" style="white-space: pre-line">{{ is_array($response->response_value) ? json_encode($response->response_value) : ($response->response_value ?? __('(no answer)')) }}</div>
            @if ($markingId === $response->id)
                <div class="row g-2 align-items-start"><div class="col-md-2"><input type="number" step="0.5" min="0" class="form-control form-control-sm" wire:model="mark" placeholder="{{ __('Mark') }} / {{ $question?->max_mark }}">@error('mark') <div class="text-danger small">{{ $message }}</div> @enderror</div><div class="col-md-8"><textarea class="form-control form-control-sm" rows="2" wire:model="feedback" placeholder="{{ __('Feedback') }}"></textarea></div><div class="col-md-2"><button type="button" class="btn btn-primary btn-sm" wire:click="save">{{ __('Save') }}</button></div></div>
            @else
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="begin({{ $response->id }})">{{ $response->mark_awarded === null ? __('Mark') : __('Re-mark') }}</button>
            @endif
        </div></div>
    @empty
        <div class="text-body-secondary">{{ __('Nothing waiting to be marked.') }}</div>
    @endforelse
</div>
