<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body>
        <div class="serp-installer">
            <div class="serp-installer-inner">
                <a href="{{ route('home') }}" class="app-brand-link gap-2 mb-4 d-inline-flex">
                    <x-app-logo-icon />
                    <span class="app-brand-text fw-semibold">{{ config('app.name', 'Laravel') }}</span>
                </a>

                <div class="serp-wizard">
                    <nav class="serp-stepper-header" aria-label="{{ __('Installation progress') }}">
                        @php
                            $completed = collect(app(\Modules\Core\Domain\Support\Install\InstallProgress::class)->completedSteps())
                                ->map(fn ($key) => $key->value)
                                ->all();
                        @endphp
                        @foreach (\Modules\Core\Domain\Support\Install\InstallStepKey::ordered() as $key)
                            @php $isDone = in_array($key->value, $completed, true) && $key !== $step; @endphp
                            <div class="serp-step {{ $key === $step ? 'is-active' : '' }} {{ $isDone ? 'is-done' : '' }}">
                                <span class="serp-step-circle">
                                    @if ($isDone)
                                        <i class="ri ri-check-line"></i>
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </span>
                                <span class="serp-step-label">{{ $key->label() }}</span>
                            </div>
                            @if (! $loop->last)
                                <div class="serp-step-line"></div>
                            @endif
                        @endforeach
                    </nav>

                    <div class="serp-wizard-pane">
                        <div class="card">
                            <div class="card-body p-4 p-sm-5">
                                {{ $slot }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="serp-toast-region" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;"></div>
    </body>
</html>
