<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Bulk textbook issue') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Issue a set of textbooks to a whole class, or collect them at term end. Learners with no available copy are listed as exceptions; the rest go ahead.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card mb-3"><div class="card-body">
                <div class="btn-group btn-group-sm mb-3" role="group">
                    <input type="radio" class="btn-check" id="m-issue" value="issue" wire:model.live="mode"><label class="btn btn-outline-secondary" for="m-issue">{{ __('Term-start issue') }}</label>
                    <input type="radio" class="btn-check" id="m-return" value="return" wire:model.live="mode"><label class="btn btn-outline-secondary" for="m-return">{{ __('Term-end collection') }}</label>
                </div>
                <select class="form-select form-select-sm mb-2" wire:model.live="classId"><option value="">{{ __('Class…') }}</option>@foreach ($classes as $class) <option value="{{ $class->id }}">{{ $class->name }}</option> @endforeach</select>
                @error('classId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <div class="border rounded p-2 mb-2" style="max-height: 14rem; overflow:auto;">
                    @forelse ($items as $item)
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="it-{{ $item->id }}" value="{{ $item->id }}" wire:model.live="itemIds"><label class="form-check-label" for="it-{{ $item->id }}">{{ $item->title }}</label></div>
                    @empty
                        <span class="text-body-secondary small">{{ __('No textbooks catalogued.') }}</span>
                    @endforelse
                </div>
                @if ($mode === 'return')
                    <select class="form-select form-select-sm mb-2" wire:model="feeComponentId"><option value="">{{ __('Fee component for unreturned books…') }}</option>@foreach ($components as $component) <option value="{{ $component->id }}">{{ $component->name }}</option> @endforeach</select>
                    @error('feeComponentId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @if ($outstanding !== [])
                        <div class="small text-body-secondary mb-1">{{ __('Tick any book that was NOT handed back — it is charged at replacement cost.') }}</div>
                        <div class="border rounded p-2 mb-2" style="max-height: 18rem; overflow:auto;">
                            @foreach ($outstanding as $studentId => $itemsOnLoan)
                                <div class="mb-1" wire:key="o-{{ $studentId }}"><strong class="small">{{ $students->get($studentId)?->fullName() }}</strong>
                                    @foreach ($itemsOnLoan as $itemId)
                                        <div class="form-check ms-3"><input class="form-check-input" type="checkbox" id="nr-{{ $studentId }}-{{ $itemId }}" value="{{ $studentId }}:{{ $itemId }}" wire:model="notReturned"><label class="form-check-label small" for="nr-{{ $studentId }}-{{ $itemId }}">{{ $titles->get($itemId) }}</label></div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
                <button type="button" class="btn btn-primary btn-sm" wire:click="run" wire:confirm="{{ $mode === 'issue' ? __('Issue these textbooks to the whole class?') : __('Process the collection? Books not handed back are charged.') }}">{{ $mode === 'issue' ? __('Issue to class') : __('Process collection') }}</button>
            </div></div>
        </div>
        <div class="col-xl-6">
            <div class="card"><div class="card-header">{{ __('Completion reports') }}</div><div class="card-body">
                @forelse ($history as $run)
                    <div class="mb-3" wire:key="h-{{ $run->id }}">
                        <div><strong>{{ $run->schoolClass?->name }}</strong> — {{ $run->issue_type === 'term_start_issue' ? __('issue') : __('collection') }} <span class="small text-body-secondary">{{ $run->created_at?->format('d M Y H:i') }}</span></div>
                        <div class="small">{{ $run->completed_count }} / {{ $run->total_learners }} {{ __('complete') }}, <span class="{{ $run->exception_count > 0 ? 'text-danger' : '' }}">{{ $run->exception_count }} {{ __('exceptions') }}</span></div>
                        @foreach ($run->exceptions ?? [] as $exception)
                            <div class="small ms-3 text-danger">{{ $students->get($exception['student_id'])?->fullName() }} — {{ $titles->get($exception['item_id']) }} ({{ str_replace('_', ' ', $exception['reason']) }})</div>
                        @endforeach
                    </div>
                @empty
                    <span class="text-body-secondary">{{ __('Nothing processed yet.') }}</span>
                @endforelse
            </div></div>
        </div>
    </div>
</div>
