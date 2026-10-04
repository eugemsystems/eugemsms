<div>
    <h4 class="mb-1">{{ __('Exeat types & quotas') }}</h4>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Types') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Notice (hrs)') }}</th><th>{{ __('Per term') }}</th></tr></thead>
                        <tbody>
                            @forelse ($types as $type)
                                <tr><td>{{ $type->code }}</td><td>{{ $type->name }}</td><td>{{ $type->min_notice_hours }}</td><td>{{ $type->allowed_per_term ?? __('Unlimited') }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No types yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Recent quota usage') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Type') }}</th><th>{{ __('Allowed') }}</th><th>{{ __('Used') }}</th><th>{{ __('Pending') }}</th></tr></thead>
                        <tbody>
                            @forelse ($quotas as $quota)
                                <tr><td>{{ $quota->student->first_name }} {{ $quota->student->last_name }}</td><td>{{ $quota->exeatType->name }}</td><td>{{ $quota->allowed }}</td><td>{{ $quota->used }}</td><td>{{ $quota->pending }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No quota activity yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New exeat type') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                        <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                        <input type="number" class="form-control mb-2" wire:model="maxDurationHours" placeholder="{{ __('Max duration hours (optional)') }}">
                        <input type="number" class="form-control mb-2" wire:model="minNoticeHours" placeholder="{{ __('Minimum notice hours') }}">
                        <input type="number" class="form-control mb-2" wire:model="allowedPerTerm" placeholder="{{ __('Allowed per term (optional)') }}">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" wire:model="requiresGuardianRequest" id="requiresGuardianRequest">
                            <label class="form-check-label" for="requiresGuardianRequest">{{ __('Requires guardian initiation') }}</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" wire:model="blocksOnSuspension" id="blocksOnSuspension">
                            <label class="form-check-label" for="blocksOnSuspension">{{ __('Blocks while suspended') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Create') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
