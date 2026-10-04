<div>
    <h4 class="mb-1">{{ __('Behaviour categories') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('At least one active safeguarding-trigger category must always remain.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Polarity') }}</th><th>{{ __('Points') }}</th><th>{{ __('Trigger') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($categories as $category)
                                <tr wire:key="category-{{ $category->id }}">
                                    <td>{{ $category->code }}</td>
                                    <td>{{ $category->name }}</td>
                                    <td><span class="badge text-bg-{{ $category->polarity === 'positive' ? 'success' : 'secondary' }}">{{ $category->polarity }}</span></td>
                                    <td>{{ $category->default_points }}</td>
                                    <td>{{ $category->is_safeguarding_trigger ? '⭐' : '' }}</td>
                                    <td>
                                        @if ($category->is_active)
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deactivate({{ $category->id }})">{{ __('Deactivate') }}</button>
                                        @else
                                            <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No categories.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New category') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="polarity">
                        <option value="positive">{{ __('Positive') }}</option>
                        <option value="negative">{{ __('Negative') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="defaultPoints" placeholder="{{ __('Default points') }}">
                    <input type="number" class="form-control mb-2" wire:model="severityLevel" placeholder="{{ __('Severity level (1-5, negative only)') }}">
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="requiresEvidence" wire:model="requiresEvidence">
                        <label class="form-check-label" for="requiresEvidence">{{ __('Requires evidence') }}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="requiresHeadReview" wire:model="requiresHeadReview">
                        <label class="form-check-label" for="requiresHeadReview">{{ __('Requires head review') }}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="autoNotifyGuardian" wire:model="autoNotifyGuardian">
                        <label class="form-check-label" for="autoNotifyGuardian">{{ __('Auto-notify guardian') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="isSafeguardingTrigger" wire:model="isSafeguardingTrigger">
                        <label class="form-check-label" for="isSafeguardingTrigger">⭐ {{ __('Safeguarding trigger — routes to BRD-08 and pauses discipline') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
