<div>
    <h4 class="mb-1">{{ __('Awards') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Type') }}</th><th>{{ __('Title') }}</th><th>{{ __('Awarded') }}</th></tr></thead>
                        <tbody>
                            @forelse ($awards as $award)
                                <tr wire:key="award-{{ $award->id }}">
                                    <td>{{ $award->student->fullName() }}</td>
                                    <td>{{ $award->award_type }}</td>
                                    <td>{{ $award->title }}</td>
                                    <td>{{ $award->awarded_on->toDateString() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No awards.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New award') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->fullName() }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="awardType">
                        @foreach (['half_colours', 'full_colours', 'honours', 'certificate', 'trophy', 'scholarship'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                    <textarea class="form-control mb-2" wire:model="citation" placeholder="{{ __('Citation (optional)') }}"></textarea>
                    <select class="form-select mb-2" wire:model="activityId">
                        <option value="">{{ __('Linked activity (optional)') }}</option>
                        @foreach ($activities as $activity)
                            <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="appearsOnReportCard" wire:model="appearsOnReportCard">
                        <label class="form-check-label" for="appearsOnReportCard">{{ __('Appears on report card') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="appearsOnTranscript" wire:model="appearsOnTranscript">
                        <label class="form-check-label" for="appearsOnTranscript">{{ __('Appears on transcript') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Record award') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
