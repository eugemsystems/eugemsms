<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('approvals.chains', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('New approval chain') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Steps run in order for a sequential chain, or resolve together for parallel modes.') }}</p>
        </div>
    </div>

    <form wire:submit="save">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">{{ __('Chain') }}</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('approvableType') is-invalid @enderror" id="approvableType" wire:model="approvableType" placeholder=" ">
                            <label for="approvableType">{{ __('Approvable type') }}</label>
                            @error('approvableType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text">{{ __('e.g. purchase_order, exeat, fee_waiver.') }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model="name" placeholder=" ">
                            <label for="name">{{ __('Name') }}</label>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="number" min="0" class="form-control @error('priority') is-invalid @enderror" id="priority" wire:model="priority" placeholder=" ">
                            <label for="priority">{{ __('Priority (lower evaluates first)') }}</label>
                            @error('priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" wire:model="description" placeholder=" ">
                            <label for="description">{{ __('Description (optional)') }}</label>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="conditionRulesJson">{{ __('Condition rules (optional JSON — omit for the default/unconditional chain)') }}</label>
                        <textarea class="form-control font-monospace @error('conditionRulesJson') is-invalid @enderror" id="conditionRulesJson" wire:model="conditionRulesJson" rows="2" placeholder='[{"field":"amount_minor","operator":">=","value":50000}]'></textarea>
                        @error('conditionRulesJson') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="isDefault" wire:model="isDefault">
                            <label class="form-check-label" for="isDefault">{{ __('This is the default (fallback) chain for this type') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @foreach ($steps as $index => $step)
            <div class="card mb-3" wire:key="step-{{ $index }}">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">{{ __('Step :number', ['number' => $index + 1]) }}</h6>
                    @if (count($steps) > 1)
                        <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeStep({{ $index }})" title="{{ __('Remove step') }}" aria-label="{{ __('Remove step') }}">
                            <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('steps.'.$index.'.name') is-invalid @enderror" id="step-{{ $index }}-name" wire:model="steps.{{ $index }}.name" placeholder=" ">
                                <label for="step-{{ $index }}-name">{{ __('Step name') }}</label>
                                @error('steps.'.$index.'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" id="step-{{ $index }}-mode" wire:model="steps.{{ $index }}.mode">
                                    <option value="sequential">{{ __('Sequential') }}</option>
                                    <option value="parallel_all">{{ __('Parallel — all must approve') }}</option>
                                    <option value="parallel_any">{{ __('Parallel — N must approve') }}</option>
                                </select>
                                <label for="step-{{ $index }}-mode">{{ __('Mode') }}</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating form-floating-outline">
                                <input type="number" min="1" class="form-control" id="step-{{ $index }}-required" wire:model="steps.{{ $index }}.requiredApprovals" placeholder=" " @disabled($step['mode'] !== 'parallel_any')>
                                <label for="step-{{ $index }}-required">{{ __('Required approvals') }}</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" id="step-{{ $index }}-approver-type" wire:model="steps.{{ $index }}.approverType">
                                    <option value="role">{{ __('Role') }}</option>
                                    <option value="user">{{ __('Specific user') }}</option>
                                    <option value="dynamic">{{ __('Dynamic resolver') }}</option>
                                </select>
                                <label for="step-{{ $index }}-approver-type">{{ __('Approver type') }}</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            @if ($step['approverType'] === 'role')
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" id="step-{{ $index }}-approver-role" wire:model="steps.{{ $index }}.approverRoleId">
                                        <option value="">{{ __('Select a role') }}</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                        @endforeach
                                    </select>
                                    <label for="step-{{ $index }}-approver-role">{{ __('Approver role') }}</label>
                                </div>
                            @elseif ($step['approverType'] === 'user')
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" id="step-{{ $index }}-approver-user" wire:model="steps.{{ $index }}.approverUserId" placeholder=" ">
                                    <label for="step-{{ $index }}-approver-user">{{ __('Approver user id') }}</label>
                                </div>
                            @else
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" id="step-{{ $index }}-dynamic" wire:model="steps.{{ $index }}.dynamicResolver" placeholder=" ">
                                    <label for="step-{{ $index }}-dynamic">{{ __('Dynamic resolver name') }}</label>
                                </div>
                                <div class="form-text">{{ __('Must be registered by the owning module — e.g. housemaster_of_learner.') }}</div>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <input type="number" min="0" class="form-control" id="step-{{ $index }}-escalate-hours" wire:model="steps.{{ $index }}.escalateAfterHours" placeholder=" ">
                                <label for="step-{{ $index }}-escalate-hours">{{ __('Escalate after (hours, optional)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" id="step-{{ $index }}-escalate-role" wire:model="steps.{{ $index }}.escalateToRoleId">
                                    <option value="">{{ __('No escalation role') }}</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                    @endforeach
                                </select>
                                <label for="step-{{ $index }}-escalate-role">{{ __('Escalate to role') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="step-{{ $index }}-condition-rules">{{ __('Skip condition (optional JSON)') }}</label>
                            <textarea class="form-control font-monospace" id="step-{{ $index }}-condition-rules" wire:model="steps.{{ $index }}.conditionRulesJson" rows="1"></textarea>
                        </div>

                        <div class="col-12 d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="step-{{ $index }}-can-reject" wire:model="steps.{{ $index }}.canReject">
                                <label class="form-check-label" for="step-{{ $index }}-can-reject">{{ __('Can reject') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="step-{{ $index }}-can-return" wire:model="steps.{{ $index }}.canReturn">
                                <label class="form-check-label" for="step-{{ $index }}-can-return">{{ __('Can return') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="step-{{ $index }}-requires-comment" wire:model="steps.{{ $index }}.requiresComment">
                                <label class="form-check-label" for="step-{{ $index }}-requires-comment">{{ __('Requires a comment') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="mb-3">
            <button type="button" class="btn btn-outline-secondary" wire:click="addStep">
                <i class="ri ri-add-line me-1"></i>{{ __('Add step') }}
            </button>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create chain') }}</button>
            <a href="{{ route('approvals.chains', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
