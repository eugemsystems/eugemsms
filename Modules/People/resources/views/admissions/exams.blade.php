<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Entrance exams') }}</h4><p class="text-body-secondary small mb-0">{{ __('Schedule, seat applicants, capture marks per paper, then rank and publish.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card mb-3"><div class="card-header">{{ __('Schedule an exam') }}</div><div class="card-body">
                <select class="form-select form-select-sm mb-2" wire:model="intakeId"><option value="">{{ __('Intake…') }}</option>@foreach ($intakes as $intake) <option value="{{ $intake->id }}">{{ $intake->name }}</option> @endforeach</select>
                <input type="text" class="form-control form-control-sm mb-2" wire:model="name" placeholder="{{ __('Name') }}">@error('name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <div class="row g-2 mb-2"><div class="col-7"><input type="date" class="form-control form-control-sm" wire:model="examDate"></div><div class="col-5"><input type="time" class="form-control form-control-sm" wire:model="startTime"></div></div>
                <div class="row g-2 mb-2"><div class="col-8"><input type="text" class="form-control form-control-sm" wire:model="venue" placeholder="{{ __('Venue') }}"></div><div class="col-4"><input type="number" class="form-control form-control-sm" wire:model="capacity" placeholder="{{ __('Seats') }}"></div></div>
                <textarea class="form-control form-control-sm mb-2" rows="3" wire:model="papersText" placeholder="{{ __("One paper per line: subject | max mark | weight\nMathematics | 100 | 50\nEnglish | 100 | 50") }}"></textarea>
                <button type="button" class="btn btn-primary btn-sm" wire:click="schedule">{{ __('Schedule') }}</button>
            </div></div>
            <div class="list-group">@foreach ($exams as $e) <button type="button" class="list-group-item list-group-item-action small d-flex justify-content-between {{ $examId === $e->id ? 'active' : '' }}" wire:key="x-{{ $e->id }}" wire:click="open({{ $e->id }})">{{ $e->name }} <span>{{ $e->exam_date->format('d M') }} · {{ $e->status }}</span></button> @endforeach</div>
        </div>
        <div class="col-xl-8">
            @if ($exam)
                <div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2"><div><strong>{{ $exam->name }}</strong> <span class="badge text-bg-light border">{{ $exam->status }}</span><div class="small text-body-secondary">{{ collect($exam->papers)->map(fn ($p) => $p['subject'].' /'.$p['max_mark'].' ×'.$p['weight'].'%')->implode(' · ') }}</div></div>
                    <div class="d-flex gap-2">@if ($exam->status === 'in_progress') <button type="button" class="btn btn-sm btn-primary" wire:click="process" wire:confirm="{{ __('Rank candidates and close marking?') }}">{{ __('Rank results') }}</button> @endif @if ($exam->status === 'marked') <button type="button" class="btn btn-sm btn-danger" wire:click="publish" wire:confirm="{{ __('Publish? Results become final.') }}">{{ __('Publish') }}</button> @endif</div></div></div>
                @if ($exam->status === 'scheduled')
                    <div class="card mb-3"><div class="card-body"><div class="input-group input-group-sm"><select class="form-select" wire:model="applicationId"><option value="">{{ __('Seat an applicant…') }}</option>@foreach ($eligible as $a) <option value="{{ $a->id }}">{{ $a->last_name }}, {{ $a->first_name }} ({{ $a->application_number }})</option> @endforeach</select><button type="button" class="btn btn-outline-primary" wire:click="seat">{{ __('Seat') }}</button></div>@error('applicationId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror</div></div>
                @endif
                <div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Candidate') }}</th><th>{{ __('Seat') }}</th>@foreach ($exam->papers as $paper) <th>{{ $paper['subject'] }}</th> @endforeach<th class="text-end">{{ __('%') }}</th><th class="text-end">{{ __('Rank') }}</th><th></th></tr></thead>
                    <tbody>@forelse ($candidates as $c) @php $app = $applications->get($c->application_id); @endphp
                        <tr wire:key="c-{{ $c->id }}"><td>{{ $app?->last_name }}, {{ $app?->first_name }}<div class="small text-body-secondary">{{ $c->candidate_number }}</div></td><td>{{ $c->seat_number }}</td>
                            @foreach ($exam->papers as $paper) <td style="width:6rem">@if (in_array($exam->status, ['scheduled', 'in_progress'])) <input type="number" step="0.5" class="form-control form-control-sm" wire:model="marks.{{ $c->id }}.{{ $paper['subject'] }}" placeholder="{{ $c->marks[$paper['subject']] ?? '' }}"> @else {{ $c->marks[$paper['subject']] ?? '—' }} @endif</td> @endforeach
                            <td class="text-end">{{ $c->attended === false ? 'absent' : ($c->percentage ?? '—') }}</td><td class="text-end">{{ $c->rank_in_exam ?? '—' }}</td>
                            <td class="text-end text-nowrap">@if (in_array($exam->status, ['scheduled', 'in_progress'])) <button type="button" class="btn btn-sm btn-outline-primary" wire:click="saveMarks({{ $c->id }}, true)">{{ __('Save') }}</button> <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="saveMarks({{ $c->id }}, false)">{{ __('Absent') }}</button> @endif @error('marks.'.$c->id) <div class="text-danger small">{{ $message }}</div> @enderror</td></tr>
                    @empty <tr><td colspan="{{ count($exam->papers) + 5 }}" class="text-center text-body-secondary py-3">{{ __('No candidates seated.') }}</td></tr> @endforelse</tbody>
                </table></div></div>
            @else <div class="text-body-secondary">{{ __('Choose or schedule an exam.') }}</div> @endif
        </div>
    </div>
</div>
