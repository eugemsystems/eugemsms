<div>
    <h4 class="mb-1">{{ __('Examination papers') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Component weights within a subject must total 100% — checked at results processing, shown here as a live advisory.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="sessionId">
                <option value="">{{ __('Select session') }}</option>
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Level') }}</th><th>{{ __('Paper') }}</th><th>{{ __('Weight %') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($papers as $paper)
                                <tr wire:key="paper-{{ $paper->id }}">
                                    <td>{{ $paper->subject?->name }}</td>
                                    <td>{{ $paper->gradeLevel?->name }}</td>
                                    <td>{{ $paper->paper_name }} (#{{ $paper->paper_number }})</td>
                                    <td>
                                        {{ $paper->weight_percent }}%
                                        @php $total = $weightTotals["{$paper->subject_id}:{$paper->grade_level_id}"] ?? 0; @endphp
                                        <span class="badge text-bg-{{ abs($total - 100) > 0.01 ? 'warning' : 'success' }}">{{ __('Total: :total%', ['total' => $total]) }}</span>
                                    </td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($paper->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No papers yet for this session.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New paper') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="subjectId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Subject') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="gradeLevelId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($gradeLevels as $level)
                                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Level') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="paperNumber">
                                    <label>{{ __('Paper #') }}</label>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('paperName') is-invalid @enderror" wire:model="paperName" placeholder=" ">
                                    <label>{{ __('Paper name') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="componentType">
                                        <option value="theory">{{ __('Theory') }}</option>
                                        <option value="practical">{{ __('Practical') }}</option>
                                        <option value="oral">{{ __('Oral') }}</option>
                                        <option value="coursework">{{ __('Coursework') }}</option>
                                    </select>
                                    <label>{{ __('Component') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="maxMark">
                                    <label>{{ __('Max mark') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" step="0.01" class="form-control" wire:model="weightPercent">
                                    <label>{{ __('Weight %') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="durationMinutes">
                                    <label>{{ __('Duration (minutes)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="setterStaffId">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($staff as $member)
                                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Setter (optional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create paper') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
