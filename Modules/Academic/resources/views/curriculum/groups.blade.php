<div>
    <h4 class="mb-1">{{ __('Subject groups') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('The join point FIN-02 prices per-subject rates against — renaming a code here changes what a bursar bills.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Groups') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Lab / Workshop') }}</th><th>{{ __('Mapped fee rate') }}</th></tr></thead>
                        <tbody>
                            @forelse ($groups as $group)
                                <tr wire:key="group-{{ $group->id }}">
                                    <td>{{ $group->code }}</td>
                                    <td>{{ $group->name }}</td>
                                    <td>
                                        @if ($group->requires_laboratory) <span class="badge text-bg-info">{{ __('Lab') }}</span> @endif
                                        @if ($group->requires_workshop) <span class="badge text-bg-info">{{ __('Workshop') }}</span> @endif
                                    </td>
                                    <td>
                                        @if (isset($rateByGroupCode[$group->code]))
                                            {{ number_format($rateByGroupCode[$group->code] / 100, 2) }}
                                        @else
                                            <span class="text-body-secondary">{{ __('Not mapped — falls back to base rate') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No subject groups yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New subject group') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder=" ">
                                    <label>{{ __('Code') }}</label>
                                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <textarea class="form-control" wire:model="description" placeholder=" " style="height: 70px"></textarea>
                                    <label>{{ __('Description (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="requiresLaboratory">
                                    <label class="form-check-label">{{ __('Requires laboratory') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="requiresWorkshop">
                                    <label class="form-check-label">{{ __('Requires workshop') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create group') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
