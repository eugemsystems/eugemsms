<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.automation.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ $automationRule ? $automationRule->name : __('New automation rule') }}</h4>
            @if ($automationRule)
                <span class="badge {{ $automationRule->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $automationRule->is_active ? __('active') : __('inactive') }}</span>
            @endif
        </div>
    </div>

    @if ($automationRule)
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">{{ __('Definition') }}</div>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item"><strong>{{ __('Notification') }}:</strong> {{ $automationRule->notification_key }}</li>
                        <li class="list-group-item"><strong>{{ __('Trigger') }}:</strong> {{ $automationRule->trigger_type === 'event' ? __('Event: :name', ['name' => $automationRule->event_name]) : __('Scan of :entity on :cron', ['entity' => $automationRule->scan_entity, 'cron' => $automationRule->schedule_cron]) }}</li>
                        <li class="list-group-item"><strong>{{ __('Delay') }}:</strong> {{ $automationRule->delay_minutes }} {{ __('min') }}</li>
                        <li class="list-group-item"><strong>{{ __('Throttle') }}:</strong> {{ $automationRule->throttle_key ? $automationRule->throttle_key.' / '.$automationRule->throttle_window_hours.'h' : __('none (notification bus dedup still applies)') }}</li>
                    </ul>
                </div>
                <div class="card">
                    <div class="card-header">{{ __('Conditions') }}</div>
                    <ul class="list-group list-group-flush small">
                        @forelse ($ruleConditions as $condition)
                            <li class="list-group-item" wire:key="cond-{{ $condition->id }}">
                                <span class="badge bg-label-secondary">{{ __('group :n', ['n' => $condition->group_id]) }}</span>
                                {{ $condition->field }} <strong>{{ $condition->operator }}</strong> {{ is_array($condition->value) ? implode(', ', array_map('json_encode', $condition->value)) : json_encode($condition->value) }}
                            </li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('No conditions — every record matches.') }}</li>
                        @endforelse
                    </ul>
                    <div class="card-footer small text-body-secondary">{{ __('All conditions in a group must match; any group matching is enough.') }}</div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">{{ __('Cost review & activation') }} ⭐</div>
                    <div class="card-body">
                        <p class="mb-2">{{ __('Estimated monthly cost') }}: <strong>{{ $estimate ?? __('not yet estimated') }}</strong></p>
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="estimateCost">{{ __('Estimate cost') }}</button>
                        @if ($automationRule->is_active)
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="deactivate" wire:confirm="{{ __('Deactivate this rule? Messages already sent are not recalled.') }}">{{ __('Deactivate') }}</button>
                        @else
                            <button type="button" class="btn btn-success btn-sm" wire:click="activate" @disabled($estimate === null)>{{ __('Activate') }}</button>
                            @if ($estimate === null) <div class="small text-body-secondary mt-2">{{ __('Estimate the cost first — a rule is never switched on blind.') }}</div> @endif
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span>{{ __('Preview (no messages sent)') }}</span>
                        <button type="button" class="btn btn-outline-primary btn-xs" wire:click="preview" @disabled($automationRule->trigger_type !== 'scheduled_scan')>{{ __('Run preview') }}</button>
                    </div>
                    @if ($automationRule->trigger_type !== 'scheduled_scan')
                        <div class="card-body small text-body-secondary">{{ __('Preview runs against current data, so it is available for scheduled-scan rules. An event rule evaluates each event as it happens.') }}</div>
                    @elseif ($previewScanned !== null)
                        <div class="card-body small">{{ __(':matched of :scanned records would receive this notification.', ['matched' => count($previewMatches), 'scanned' => $previewScanned]) }} @if (count($previewMatches) === 50) {{ __('(first 50 shown)') }} @endif</div>
                        <ul class="list-group list-group-flush small">
                            @foreach ($previewMatches as $match)
                                <li class="list-group-item" wire:key="pm-{{ $loop->index }}">{{ $match['subject_type'] }} #{{ $match['subject_id'] }} — {{ collect($match['context'])->map(fn ($v, $k) => $k.': '.(is_scalar($v) ? $v : json_encode($v)))->take(4)->implode(', ') }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">{{ __('Rule') }}</div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name, e.g. Fee overdue — 30 day notice') }}">
                        @error('name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <select class="form-select mb-2" wire:model="notificationKey">
                            <option value="">{{ __('Notification to send…') }}</option>
                            @foreach ($notificationKeys as $key) <option value="{{ $key }}">{{ $key }}</option> @endforeach
                        </select>
                        @error('notificationKey') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <select class="form-select mb-2" wire:model.live="triggerType">
                            <option value="scheduled_scan">{{ __('Scheduled scan of data') }}</option>
                            <option value="event">{{ __('When an event happens') }}</option>
                        </select>
                        @if ($triggerType === 'event')
                            <select class="form-select mb-2" wire:model.live="eventName">
                                <option value="">{{ __('Event…') }}</option>
                                @foreach ($eventNames as $event) <option value="{{ $event }}">{{ $event }}</option> @endforeach
                            </select>
                            @error('eventName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @else
                            <select class="form-select mb-2" wire:model.live="scanEntity">
                                <option value="">{{ __('Entity to scan…') }}</option>
                                @foreach ($entityKeys as $entity) <option value="{{ $entity }}">{{ $entity }}</option> @endforeach
                            </select>
                            @error('scanEntity') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                            <input type="text" class="form-control mb-2" wire:model="scheduleCron" placeholder="{{ __('Cron, e.g. 0 8 * * *') }}">
                            @error('scheduleCron') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @endif
                        <label class="form-label small mb-0">{{ __('Delay (minutes)') }}</label>
                        <input type="number" class="form-control mb-2" wire:model="delayMinutes" min="0">
                        <input type="text" class="form-control mb-2" wire:model="throttleKey" placeholder="{{ __('Throttle key, e.g. invoice.id (optional)') }}">
                        <input type="number" class="form-control mb-2" wire:model="throttleWindowHours" min="1" placeholder="{{ __('Throttle window, hours (optional)') }}">
                        @error('throttleWindowHours') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span>{{ __('Conditions') }}</span>
                        <span class="small text-body-secondary">{{ __('AND within a group, OR across groups') }}</span>
                    </div>
                    <div class="card-body">
                        @foreach ($conditions as $index => $condition)
                            <div class="row g-2 mb-2 align-items-center" wire:key="cond-row-{{ $index }}">
                                <div class="col-2"><input type="number" class="form-control form-control-sm" wire:model="conditions.{{ $index }}.group_id" min="1" title="{{ __('Group') }}"></div>
                                <div class="col-4">
                                    <select class="form-select form-select-sm" wire:model="conditions.{{ $index }}.field">
                                        <option value="">{{ __('Field…') }}</option>
                                        @foreach ($allowedFields as $field) <option value="{{ $field }}">{{ $field }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="col-2">
                                    <select class="form-select form-select-sm" wire:model="conditions.{{ $index }}.operator">
                                        @foreach ($operators as $operator) <option value="{{ $operator }}">{{ $operator }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="col-3"><input type="text" class="form-control form-control-sm" wire:model="conditions.{{ $index }}.value" placeholder="{{ __('Value') }}"></div>
                                <div class="col-1"><button type="button" class="btn btn-sm btn-icon btn-outline-danger" wire:click="removeCondition({{ $index }})"><i class="ri ri-close-line"></i></button></div>
                            </div>
                        @endforeach
                        @error('conditions') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @error('conditions.*') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="addCondition(1)">{{ __('Add condition') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="addGroup">{{ __('Add OR group') }}</button>
                        <div class="small text-body-secondary mt-2">{{ __('For "in" / "not in" separate values with commas. The field list only offers fields the owning module has exposed for automation.') }}</div>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="save">{{ __('Save rule (inactive)') }}</button>
            </div>
        </div>
    @endif
</div>
