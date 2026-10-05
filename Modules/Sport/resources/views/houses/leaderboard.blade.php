<div>
    <h4 class="mb-1">{{ __('House leaderboard') }}</h4>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('House') }}</th><th>{{ __('Total points') }}</th><th>{{ __('By source') }}</th></tr></thead>
                <tbody>
                    @forelse ($leaderboard as $entry)
                        <tr>
                            <td>{{ $entry->houseName }}</td>
                            <td class="fw-bold">{{ number_format($entry->totalPoints, 1) }}</td>
                            <td class="small text-body-secondary">
                                @foreach ($entry->bySource as $source => $points)
                                    {{ $source }}: {{ number_format($points, 1) }}&nbsp;
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No houses.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">{{ __('New house competition') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="competitionName" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="competitionType">
                        @foreach (['sport', 'academic', 'cultural', 'conduct', 'attendance', 'room_inspection'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <p class="small text-body-secondary mb-2">{{ __('Points scheme: 1st=10, 2nd=7, 3rd=5') }}</p>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createCompetition">{{ __('Create competition') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Record competition result') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="resultCompetitionId">
                        <option value="">{{ __('Competition') }}</option>
                        @foreach ($competitions as $competition)
                            <option value="{{ $competition->id }}">{{ $competition->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="resultHouseId">
                        <option value="">{{ __('House') }}</option>
                        @foreach ($houses as $house)
                            <option value="{{ $house->id }}">{{ $house->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" min="1" class="form-control mb-2" wire:model="resultPlace" placeholder="{{ __('Place') }}">
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="recordCompetitionResult">{{ __('Record placing') }}</button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('Manual points') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="manualHouseId">
                        <option value="">{{ __('House') }}</option>
                        @foreach ($houses as $house)
                            <option value="{{ $house->id }}">{{ $house->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.1" class="form-control mb-2" wire:model="manualPoints" placeholder="{{ __('Points') }}">
                    <input type="text" class="form-control mb-2" wire:model="manualReason" placeholder="{{ __('Reason (optional)') }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="recordManualPoints">{{ __('Record points') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
