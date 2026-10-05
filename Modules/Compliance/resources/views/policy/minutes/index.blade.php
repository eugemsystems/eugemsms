<div>
    <h4 class="mb-1">{{ __('Governance minutes') }}</h4>
    <p class="text-body-secondary small">{{ __('Confidential minutes are restricted to named roles, and access is logged.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Body') }}</th><th>{{ __('Meeting date') }}</th><th>{{ __('Confidentiality') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($minutes as $minute)
                                <tr wire:key="minute-{{ $minute->id }}">
                                    <td>{{ str_replace('_', ' ', $minute->body) }}</td>
                                    <td>{{ $minute->meeting_date->toDateString() }}</td>
                                    <td><span class="badge {{ $minute->confidentiality === 'confidential' ? 'bg-danger' : ($minute->confidentiality === 'restricted' ? 'bg-warning text-dark' : 'bg-light text-dark border') }}">{{ $minute->confidentiality }}</span></td>
                                    <td class="text-end"><button type="button" class="btn btn-outline-secondary btn-sm" wire:click="open({{ $minute->id }})">{{ __('Open') }}</button></td>
                                </tr>
                                @if ($openedMinuteId === $minute->id && $openedMinute)
                                    <tr>
                                        <td colspan="4">
                                            <strong>{{ __('Attendees:') }}</strong> {{ implode(', ', $openedMinute->attendees) }}<br>
                                            @if ($openedMinute->resolutions)
                                                <strong>{{ __('Resolutions:') }}</strong> {{ implode('; ', $openedMinute->resolutions) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No minutes recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Record minute') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="body">
                        <option value="board">{{ __('Board') }}</option>
                        <option value="finance_committee">{{ __('Finance committee') }}</option>
                        <option value="disciplinary">{{ __('Disciplinary') }}</option>
                        <option value="academic_board">{{ __('Academic board') }}</option>
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="meetingDate">
                    <input type="text" class="form-control mb-2" wire:model="attendeesText" placeholder="{{ __('Attendees, comma-separated') }}">
                    <select class="form-select mb-2" wire:model="confidentiality">
                        <option value="open">{{ __('Open') }}</option>
                        <option value="restricted">{{ __('Restricted') }}</option>
                        <option value="confidential">{{ __('Confidential') }}</option>
                    </select>
                    <textarea class="form-control mb-2" wire:model="resolutionsText" placeholder="{{ __('Resolutions, one per line (optional)') }}"></textarea>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
