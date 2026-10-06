<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Generate report cards') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Cards are generated and stored for approved results. A learner over the fee threshold is generated too, but held back from publication.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-5"><div class="card"><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model.live="classId"><option value="">{{ __('A class…') }}</option>@foreach ($classes as $class) <option value="{{ $class->id }}">{{ $class->name }}</option> @endforeach</select>
            <div class="text-center small text-body-secondary mb-2">{{ __('or') }}</div>
            <select class="form-select form-select-sm mb-2" wire:model.live="gradeLevelId"><option value="">{{ __('A whole grade level…') }}</option>@foreach ($gradeLevels as $level) <option value="{{ $level->id }}">{{ $level->name }}</option> @endforeach</select>
            <select class="form-select form-select-sm mb-2" wire:model="templateId"><option value="">{{ __('Default template') }}</option>@foreach ($templates as $template) <option value="{{ $template->id }}">{{ $template->name }} (v{{ $template->version }})</option> @endforeach</select>
            @error('classId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="generate" wire:loading.attr="disabled">{{ __('Generate') }}</button>
        </div></div></div>
        <div class="col-lg-7"><div class="card"><div class="card-header">{{ __('Recent runs') }}</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('When') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Generated') }}</th><th class="text-end">{{ __('Withheld') }}</th><th class="text-end">{{ __('Failed') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>@forelse ($runs as $run) <tr wire:key="run-{{ $run->id }}"><td>{{ $run->started_at?->format('d M H:i') }}</td><td class="text-end">{{ $run->total_count }}</td><td class="text-end">{{ $run->generated_count }}</td><td class="text-end">{{ $run->withheld_count }}</td><td class="text-end {{ $run->failed_count ? 'text-danger' : '' }}">{{ $run->failed_count }}</td><td>{{ $run->status }}</td></tr> @empty <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No runs yet.') }}</td></tr> @endforelse</tbody>
        </table></div></div></div>
    </div>
</div>
