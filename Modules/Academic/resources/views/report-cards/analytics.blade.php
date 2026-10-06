<div>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('Performance analytics') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('This term\'s results by subject, class and teacher, and the school mean across recent terms.') }}</p>
        </div>
        <select class="form-select form-select-sm w-auto" wire:model.live="classId"><option value="">{{ __('Whole school') }}</option>@foreach ($classes as $class) <option value="{{ $class->id }}">{{ $class->name }}</option> @endforeach</select>
    </div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="card-header">{{ __('By subject') }}</div><div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Subject') }}</th><th class="text-end">{{ __('Learners') }}</th><th class="text-end">{{ __('Mean') }}</th><th class="text-end">{{ __('Pass %') }}</th><th>{{ __('Distribution (0–9 … 90+)') }}</th></tr></thead>
            <tbody>@forelse ($data['subjects'] as $row)
                @php $max = max(1, max($row['distribution'])); @endphp
                <tr wire:key="s-{{ $row['subject_id'] }}"><td>{{ $row['subject'] }}</td><td class="text-end">{{ $row['learners'] }}</td><td class="text-end">{{ $row['mean'] }}</td><td class="text-end">{{ $row['pass_rate'] }}</td>
                    <td><div class="d-flex align-items-end gap-1" style="height: 1.6rem;">@foreach ($row['distribution'] as $bucket => $count) <div class="bg-primary" title="{{ $bucket * 10 }}–{{ $bucket * 10 + 9 }}: {{ $count }}" style="width: .6rem; height: {{ max(2, $count / $max * 100) }}%; opacity: {{ $count ? 1 : .2 }};"></div> @endforeach</div></td></tr>
            @empty <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No computed results this term.') }}</td></tr> @endforelse</tbody>
        </table></div></div></div>
        <div class="col-xl-4">
            <div class="card mb-3"><div class="card-header">{{ __('By class') }}</div><ul class="list-group list-group-flush small">@foreach ($data['classes'] as $row) <li class="list-group-item d-flex justify-content-between" wire:key="c-{{ $row['class_id'] }}"><span>{{ $row['class'] }}</span><span>{{ $row['mean'] }}% <span class="text-body-secondary">({{ $row['learners'] }})</span></span></li> @endforeach</ul></div>
            <div class="card mb-3"><div class="card-header">{{ __('By teacher') }}</div><ul class="list-group list-group-flush small">@foreach ($data['teachers'] as $row) <li class="list-group-item d-flex justify-content-between" wire:key="t-{{ $row['staff_id'] }}"><span>{{ $row['teacher'] }}</span><span>{{ $row['mean'] }}%</span></li> @endforeach</ul></div>
            <div class="card"><div class="card-header">{{ __('School mean by term') }}</div><ul class="list-group list-group-flush small">@foreach ($data['trend'] as $row) <li class="list-group-item d-flex justify-content-between" wire:key="tr-{{ $row['term_id'] }}"><span>{{ $row['term'] }}</span><span>{{ $row['mean'] }}%</span></li> @endforeach</ul></div>
        </div>
    </div>
</div>
