<x-layouts::auth :title="__('Email verification')" illustration="login">
    <h4 class="mb-1">{{ __('Verify your email ✉️') }}</h4>
    <p class="mb-0">
        {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <p class="text-success fw-medium mt-3 mb-0">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="my-4">
        @csrf
        <button type="submit" class="btn btn-primary d-grid w-100">
            {{ __('Resend verification email') }}
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="btn btn-link p-0" data-test="logout-button">
            {{ __('Log out') }}
        </button>
    </form>
</x-layouts::auth>
