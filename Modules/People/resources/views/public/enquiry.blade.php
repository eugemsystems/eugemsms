<x-layouts::auth :title="__('Admissions enquiry')">
    <h4 class="mb-1">{{ $school->name }}</h4>
    <p class="mb-4">{{ $intake->name }} — {{ __('leave your details and the admissions office will contact you.') }}</p>

    @if (session('enquiry_sent'))
        <div class="alert alert-success" role="alert">{{ __('Thank you. Your enquiry has been received and we will be in touch shortly.') }}</div>
    @else
        <form method="POST" action="{{ route('people.public.enquiry.store', $intake->public_form_slug) }}">
            @csrf
            <input type="hidden" name="started_at" value="{{ $startedAt }}">
            <div style="position:absolute;left:-9999px" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            @foreach ([['enquirer_name', __('Your name'), 'text'], ['enquirer_phone', __('Phone'), 'tel'], ['enquirer_email', __('Email'), 'email'], ['learner_name', __("Learner's name"), 'text'], ['learner_dob', __("Learner's date of birth"), 'date']] as [$field, $label, $type])
                <div class="form-floating form-floating-outline mb-4">
                    <input type="{{ $type }}" class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" placeholder="{{ $label }}">
                    <label for="{{ $field }}">{{ $label }}</label>
                    @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endforeach

            <div class="form-floating form-floating-outline mb-4">
                <select class="form-select" id="interested_grade_level_id" name="interested_grade_level_id">
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}" @selected((int) old('interested_grade_level_id', $intake->grade_level_id) === $grade->id)>{{ $grade->name }}</option>
                    @endforeach
                </select>
                <label for="interested_grade_level_id">{{ __('Grade of interest') }}</label>
            </div>

            <div class="form-floating form-floating-outline mb-4">
                <textarea class="form-control" id="message" name="message" style="height:100px" placeholder="{{ __('Message') }}">{{ old('message') }}</textarea>
                <label for="message">{{ __('Message (optional)') }}</label>
            </div>

            <button type="submit" class="btn btn-primary d-grid w-100">{{ __('Send enquiry') }}</button>
        </form>
    @endif
</x-layouts::auth>
