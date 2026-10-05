<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Automation rules') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Rules decide when a notification is sent; the notification bus still owns recipients, templates, quiet hours and budget. A rule is never switched on without reviewing its estimated monthly cost.') }}</p>
        </div>
        @if ($canManage)
            <a href="{{ route('comms.automation.create', $school) }}" class="btn btn-primary btn-sm" wire:navigate>{{ __('New rule') }}</a>
        @endif
    </div>

    <div class="d-flex gap-2 mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="triggerFilter">
            <option value="">{{ __('All triggers') }}</option>
            <option value="event">{{ __('Event') }}</option>
            <option value="scheduled_scan">{{ __('Scheduled scan') }}</option>
        </select>
        <select class="form-select form-select-sm w-auto" wire:model.live="activeFilter">
            <option value="">{{ __('Active & inactive') }}</option>
            <option value="active">{{ __('Active') }}</option>
            <option value="inactive">{{ __('Inactive') }}</option>
        </select>
        <a href="{{ route('comms.automation.executions', $school) }}" class="btn btn-outline-secondary btn-sm ms-auto" wire:navigate>{{ __('Execution log') }}</a>
        <a href="{{ route('comms.automation.scans', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Scan history') }}</a>
        <a href="{{ route('comms.automation.variants', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('A/B performance') }}</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Notification') }}</th><th>{{ __('Trigger') }}</th><th class="text-end">{{ __('Est. monthly cost') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rules as $row)
                        @php $rule = $row['rule']; @endphp
                        <tr wire:key="rule-{{ $rule->id }}">
                            <td><a href="{{ route('comms.automation.builder', [$school, $rule->ulid]) }}" wire:navigate>{{ $rule->name }}</a></td>
                            <td class="small">{{ $rule->notification_key }}</td>
                            <td class="small">{{ $rule->trigger_type === 'event' ? __('Event: :name', ['name' => $rule->event_name]) : __('Scan: :entity (:cron)', ['entity' => $rule->scan_entity, 'cron' => $rule->schedule_cron]) }}</td>
                            <td class="text-end">{{ $row['cost'] ?? '—' }}</td>
                            <td><span class="badge {{ $rule->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $rule->is_active ? __('active') : __('inactive') }}</span></td>
                            <td class="text-end">
                                @if ($rule->is_active)
                                    @if ($canManage)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="deactivate({{ $rule->id }})" wire:confirm="{{ __('Deactivate this rule? Messages already sent are not recalled.') }}">{{ __('Deactivate') }}</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No automation rules yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
