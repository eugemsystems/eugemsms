<div>
    <h4 class="mb-1">{{ __('Record behaviour') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Commendation is recorded as readily as misconduct — positive categories come first.') }}</p>

    <div class="card" style="max-width: 640px">
        <div class="card-body">
            <select class="form-select mb-2" wire:model="studentId">
                <option value="">{{ __('Student') }}</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                @endforeach
            </select>

            <div class="mb-2">
                <div class="small text-success fw-bold mb-1">{{ __('Positive') }}</div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @foreach ($positiveCategories as $category)
                        <button type="button" class="btn btn-sm {{ $categoryId === $category->id ? 'btn-success' : 'btn-outline-success' }}" wire:click="$set('categoryId', {{ $category->id }})">{{ $category->name }} (+{{ $category->default_points }})</button>
                    @endforeach
                </div>
                <div class="small text-secondary fw-bold mb-1">{{ __('Negative') }}</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($negativeCategories as $category)
                        <button type="button" class="btn btn-sm {{ $categoryId === $category->id ? 'btn-secondary' : 'btn-outline-secondary' }}" wire:click="$set('categoryId', {{ $category->id }})">
                            {{ $category->name }} @if ($category->is_safeguarding_trigger) ⭐ @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <textarea class="form-control mb-2" wire:model="description" placeholder="{{ __('Description') }}"></textarea>
            <input type="text" class="form-control mb-2" wire:model="location" placeholder="{{ __('Location (optional)') }}">
            <select class="form-select mb-2" wire:model="context">
                <option value="">{{ __('Context (optional)') }}</option>
                <option value="lesson">{{ __('Lesson') }}</option>
                <option value="prep">{{ __('Prep') }}</option>
                <option value="dining">{{ __('Dining') }}</option>
                <option value="hostel">{{ __('Hostel') }}</option>
                <option value="sport">{{ __('Sport') }}</option>
                <option value="transport">{{ __('Transport') }}</option>
                <option value="off_campus">{{ __('Off campus') }}</option>
            </select>
            <button type="button" class="btn btn-primary" wire:click="record">{{ __('Record') }}</button>
        </div>
    </div>
</div>
