<div>
    <h4 class="mb-1">{{ __('Policy register') }}</h4>
    <p class="text-body-secondary small">{{ __('A new version is always a new row. Prior acknowledgements of an earlier version remain on record and never count towards the current one.') }}</p>

    <button type="button" class="btn btn-outline-info btn-sm mb-3" wire:click="checkReviewDue">{{ __('Check review-due policies') }}</button>
    @if ($overdueCount > 0)
        <div class="alert alert-warning">{{ __(':count polic(ies) past their review date.', ['count' => $overdueCount]) }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Title') }}</th><th>{{ __('Version') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($policies as $policy)
                                <tr wire:key="pol-{{ $policy->id }}">
                                    <td>{{ $policy->code }}</td>
                                    <td>{{ $policy->title }}</td>
                                    <td>{{ $policy->version }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $policy->status }}</span></td>
                                    <td class="text-end">
                                        @if ($policy->requires_acknowledgement)
                                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="acknowledge({{ $policy->id }})">{{ __('Acknowledge') }}</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="viewStatus({{ $policy->id }})">{{ __('Status') }}</button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($selectedPolicyId === $policy->id && $acknowledgementStatus)
                                    <tr>
                                        <td colspan="5">
                                            @foreach ($acknowledgementStatus as $audience => $status)
                                                <div class="small">{{ $audience }}: {{ count($status['acknowledged']) }} {{ __('acknowledged') }}, {{ count($status['outstanding']) }} {{ __('outstanding') }}</div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No policies yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New policy version') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                    <input type="text" class="form-control mb-2" wire:model="category" placeholder="{{ __('Category') }}">
                    <input type="text" class="form-control mb-2" wire:model="version" placeholder="{{ __('Version') }}">
                    <input type="date" class="form-control mb-2" wire:model="effectiveFrom">
                    <input type="date" class="form-control mb-2" wire:model="reviewDueOn" placeholder="{{ __('Review due (optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="supersedesPolicyId" placeholder="{{ __('Supersedes policy id (optional)') }}">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="requiresAcknowledgement" id="polRequiresAck">
                        <label class="form-check-label small" for="polRequiresAck">{{ __('Requires acknowledgement') }}</label>
                    </div>
                    <div class="mb-2 small">
                        <label><input type="checkbox" wire:model="acknowledgementAudiences" value="staff"> {{ __('Staff') }}</label>
                        <label class="ms-2"><input type="checkbox" wire:model="acknowledgementAudiences" value="guardians"> {{ __('Guardians') }}</label>
                        <label class="ms-2"><input type="checkbox" wire:model="acknowledgementAudiences" value="learners"> {{ __('Learners') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
