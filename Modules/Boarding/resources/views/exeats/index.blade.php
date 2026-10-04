<div>
    <h4 class="mb-1">{{ __('Exeat requests') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Staff-recorded on a guardian\'s behalf requires request_source = phone_recorded, flagged on the approval screen (BR-BRD-03-001).') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Type') }}</th><th>{{ __('Departs') }}</th><th>{{ __('Returns by') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($exeats as $exeat)
                                <tr>
                                    <td>{{ $exeat->student->first_name }} {{ $exeat->student->last_name }}</td>
                                    <td>{{ $exeat->exeatType->name }}</td>
                                    <td>{{ $exeat->departs_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ $exeat->returns_by->format('Y-m-d H:i') }}</td>
                                    <td><span class="badge text-bg-{{ in_array($exeat->status, ['overdue'], true) ? 'danger' : ($exeat->status === 'returned' ? 'success' : 'secondary') }}">{{ ucfirst($exeat->status) }}</span></td>
                                    <td class="text-end"><a href="{{ route('boarding.exeats.show', [$school, $exeat]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('View') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No exeats yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Record exeat request') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="studentId">
                        <option value="">{{ __('Learner') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="exeatTypeId">
                        <option value="">{{ __('Exeat type') }}</option>
                        @foreach ($exeatTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <textarea class="form-control mb-2 @error('reason') is-invalid @enderror" wire:model="reason" placeholder="{{ __('Reason') }}"></textarea>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="datetime-local" class="form-control" wire:model="departsAt"></div>
                        <div class="col-6"><input type="datetime-local" class="form-control" wire:model="returnsBy"></div>
                    </div>
                    <input type="text" class="form-control mb-2 @error('destinationAddress') is-invalid @enderror" wire:model="destinationAddress" placeholder="{{ __('Destination address') }}">
                    <input type="text" class="form-control mb-2" wire:model="destinationProvince" placeholder="{{ __('Destination province') }}">
                    <input type="text" class="form-control mb-2" wire:model="contactPhone" placeholder="{{ __('Contact phone while away') }}">
                    <select class="form-select mb-2" wire:model="collectionMethod">
                        <option value="guardian_collect">{{ __('Guardian collects') }}</option>
                        <option value="authorised_person">{{ __('Authorised person') }}</option>
                        <option value="school_transport">{{ __('School transport') }}</option>
                        <option value="public_transport">{{ __('Public transport') }}</option>
                        <option value="self_travel">{{ __('Self travel') }}</option>
                    </select>
                    @if ($guardians->isNotEmpty())
                        <select class="form-select mb-2" wire:model="collectingGuardianId">
                            <option value="">{{ __('Collecting guardian') }}</option>
                            @foreach ($guardians as $sg)
                                <option value="{{ $sg->guardian_id }}">{{ $sg->guardian?->displayName() ?? $sg->guardian_id }} @if ($sg->has_court_restriction) ⚠ @endif</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Request') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
