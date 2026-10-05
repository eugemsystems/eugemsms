<div>
    <h4 class="mb-1">{{ __('Fixtures') }}</h4>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Team') }}</th><th>{{ __('Opponent') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($fixtures as $fixture)
                                <tr wire:key="fixture-{{ $fixture->id }}" class="{{ $selectedFixtureId === $fixture->id ? 'table-active' : '' }}">
                                    <td>{{ $fixture->team->name }}</td>
                                    <td>{{ $fixture->opponent }}</td>
                                    <td>{{ $fixture->fixture_date->toDateString() }}</td>
                                    <td>{{ $fixture->status }}{{ $fixture->result ? ' — '.$fixture->result : '' }}</td>
                                    <td><button type="button" class="btn btn-outline-secondary btn-sm" wire:click="select({{ $fixture->id }})">{{ __('Select') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No fixtures.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Schedule fixture') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="teamId">
                        <option value="">{{ __('Team') }}</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="opponent" placeholder="{{ __('Opponent') }}">
                    <select class="form-select mb-2" wire:model="fixtureType">
                        @foreach (['friendly', 'league', 'cup', 'tournament', 'inter_house'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model.live="venueType">
                        <option value="home">{{ __('Home') }}</option>
                        <option value="away">{{ __('Away') }}</option>
                        <option value="neutral">{{ __('Neutral') }}</option>
                    </select>
                    @if ($venueType === 'away')
                        <input type="text" class="form-control mb-2" wire:model="venueName" placeholder="{{ __('Away venue name') }}">
                    @endif
                    <input type="date" class="form-control mb-2" wire:model="fixtureDate">
                    <input type="time" class="form-control mb-2" wire:model="startTime" placeholder="{{ __('Start time (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="schedule">{{ __('Schedule fixture') }}</button>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            @if ($selectedFixtureId !== null)
                <div class="card mb-3">
                    <div class="card-header">{{ __('Confirm fixture #') }}{{ $selectedFixtureId }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="vehicleId">
                            <option value="">{{ __('Vehicle (away fixtures)') }}</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ $vehicle->fleet_number }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="driverId">
                            <option value="">{{ __('Driver (away fixtures)') }}</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->staff->fullName() }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="escortStaffId">
                            <option value="">{{ __('Escort staff (optional)') }}</option>
                            @foreach ($escorts as $escort)
                                <option value="{{ $escort->id }}">{{ $escort->fullName() }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="resourceId">
                            <option value="">{{ __('Venue resource (home fixtures)') }}</option>
                            @foreach ($resources as $resource)
                                <option value="{{ $resource->id }}">{{ $resource->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="confirm">{{ __('Confirm fixture') }}</button>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">{{ __('Squad & roll status') }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="squadStudentIds" multiple size="6">
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}">{{ $student->fullName() }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-2" wire:click="selectSquad">{{ __('Select squad') }}</button>

                        @if ($rollCalls->isNotEmpty())
                            <select class="form-select mb-2" wire:model="rollCallIds" multiple size="4">
                                @foreach ($rollCalls as $rollCall)
                                    <option value="{{ $rollCall->id }}">{{ $rollCall->roll_date->toDateString() }} {{ $rollCall->scheduled_at->format('H:i') }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="markRollStatus">{{ __("Mark squad roll status 'fixture'") }}</button>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">{{ __('Record result') }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="result">
                            @foreach (['won', 'lost', 'drew', 'cancelled', 'postponed'] as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                        <input type="text" class="form-control mb-2" wire:model="scoreFor" placeholder="{{ __('Score for (optional)') }}">
                        <input type="text" class="form-control mb-2" wire:model="scoreAgainst" placeholder="{{ __('Score against (optional)') }}">
                        <button type="button" class="btn btn-dark btn-sm" wire:click="recordResult">{{ __('Record result') }}</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">{{ __('Record injury') }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="injuryStudentId">
                            <option value="">{{ __('Injured student') }}</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}">{{ $student->fullName() }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="injuryType">
                            <option value="minor_injury">{{ __('Minor injury') }}</option>
                            <option value="serious_injury">{{ __('Serious injury') }}</option>
                            <option value="head_injury">{{ __('Head injury') }}</option>
                        </select>
                        <textarea class="form-control mb-2" wire:model="injuryDescription" placeholder="{{ __('Description') }}"></textarea>
                        <select class="form-select mb-2" wire:model="injurySeverity">
                            @foreach (['minor', 'moderate', 'serious', 'critical'] as $sev)
                                <option value="{{ $sev }}">{{ $sev }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-danger btn-sm" wire:click="recordInjury">{{ __('Record injury') }}</button>
                    </div>
                </div>
            @else
                <div class="text-body-secondary">{{ __('Select a fixture to confirm, select its squad, or record a result.') }}</div>
            @endif
        </div>
    </div>
</div>
