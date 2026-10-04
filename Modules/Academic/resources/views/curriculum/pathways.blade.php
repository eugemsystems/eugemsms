<div>
    <h4 class="mb-1">{{ __('Pathways') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('The two-route model — below the applying level, a learner\'s pathway stays null.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Pathways') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Framework') }}</th><th>{{ __('Applies from level') }}</th><th>{{ __('Default') }}</th></tr></thead>
                        <tbody>
                            @forelse ($pathways as $pathway)
                                <tr wire:key="pathway-{{ $pathway->id }}">
                                    <td>{{ $pathway->code }}</td>
                                    <td>{{ $pathway->name }}</td>
                                    <td>{{ $pathway->framework?->code }}</td>
                                    <td>{{ $pathway->applies_from_level_ordinal }}</td>
                                    <td>{{ $pathway->is_default ? __('Yes') : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No pathways yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New pathway') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="frameworkId">
                                        @foreach ($frameworks as $framework)
                                            <option value="{{ $framework->id }}">{{ $framework->code }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Framework') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder=" ">
                                    <label>{{ __('Code') }}</label>
                                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control @error('appliesFromLevelOrdinal') is-invalid @enderror" wire:model="appliesFromLevelOrdinal" placeholder=" ">
                                    <label>{{ __('Applies from level ordinal') }}</label>
                                    @error('appliesFromLevelOrdinal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="isDefault">
                                    <label class="form-check-label">{{ __('Default pathway') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <textarea class="form-control" wire:model="description" placeholder=" " style="height: 70px"></textarea>
                                    <label>{{ __('Description (optional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create pathway') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
