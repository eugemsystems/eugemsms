<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.complaints.queue', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Complaint categories') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('A category sets the response deadline. A category flagged for safeguarding sends every complaint under it to the safeguarding team instead of this queue. Categories cannot be edited, so open complaints keep the deadline they were given.') }}</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th class="text-end">{{ __('SLA (hours)') }}</th><th>{{ __('Safeguarding') }}</th></tr></thead>
                        <tbody>
                            @forelse ($categories as $category)
                                <tr wire:key="cat-{{ $category->id }}">
                                    <td>{{ $category->code }}</td>
                                    <td>{{ $category->name }}</td>
                                    <td class="text-end">{{ $category->sla_hours }}</td>
                                    <td>@if ($category->is_safeguarding_trigger) <span class="badge bg-label-danger">{{ __('routes to safeguarding') }}</span> @else — @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No categories yet — complaints cannot be raised until one exists.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New category') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code, e.g. fees') }}">
                    @error('code') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    @error('name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <label class="form-label small mb-0">{{ __('Response deadline (hours)') }}</label>
                    <input type="number" class="form-control mb-2" wire:model="slaHours" min="1">
                    @error('slaHours') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="cat-sg" wire:model="isSafeguardingTrigger"><label class="form-check-label small" for="cat-sg">{{ __('Route complaints in this category to safeguarding') }}</label></div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create category') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
