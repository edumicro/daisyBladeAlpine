{{--
    Tipo 3: Multi-step wizard with Alpine + Axios + localStorage persistence.

    @prop steps   — [ ['label'=>'Info', 'description'=>'', 'schema'=>[...], 'values'=>[...]] ]
    @prop action  — POST endpoint URL
    @prop method  — HTTP method (default POST)
    @prop submitLabel — last step button label (default 'Finish')
    @prop userId  — user ID for localStorage key isolation
    @prop formId  — form identifier for localStorage key
    @prop schemaVersion — bump to reset persisted state

    Server contract:
      success: 200/201 with { redirect?: string, message?: string }
      error:   422 with { errors: { field: ['msg'] } }
      global:  500 with { message: 'error text' }
--}}
@props([
    'steps'         => [],
    'action'        => '',
    'method'        => 'POST',
    'submitLabel'   => '',
    'userId'        => '',
    'formId'        => 'wizard',
    'schemaVersion' => 1,
    'class'         => '',
    'containerClass' => '',
])

@php
$stepCount  = count($steps);
$initValues = [];
foreach ($steps as $step) {
    foreach (($step['values'] ?? []) as $k => $v) {
        $initValues[$k] = $v;
    }
}
@endphp

<div
    x-data="dbWizard({
        action:        '{{ $action }}',
        method:        '{{ strtoupper($method) }}',
        userId:        '{{ $userId }}',
        formId:        '{{ $formId }}',
        schemaVersion: {{ $schemaVersion }},
    })"
    x-init="
        init();
        Object.assign(data, @js($initValues));
    "
    {{ $attributes->merge(['class' => "space-y-6 {$containerClass}"]) }}
>
    {{-- Steps indicator --}}
    <ul class="steps w-full">
        @foreach($steps as $i => $step)
            <li
                :data-content="step > {{ $i }} ? '✓' : '{{ $i + 1 }}'"
                :class="{
                    'step-success': step > {{ $i }},
                    'step-primary': step === {{ $i }}
                }"
                class="step"
            >
                <div class="flex flex-col gap-0.5 text-left">
                    <span class="text-sm font-medium leading-tight">{{ $step['label'] ?? '' }}</span>
                    @if(!empty($step['description']))
                        <span class="text-xs text-base-content/50">{{ $step['description'] }}</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    {{-- Global error --}}
    <div x-show="Object.keys(errors).length > 0 || globalError" x-cloak class="alert alert-error">
        <x-heroicon-o-x-circle class="w-5 h-5 flex-shrink-0" />
        <div>
            <template x-if="globalError">
                <p x-text="globalError"></p>
            </template>
            <template x-if="!globalError && Object.keys(errors).length">
                <p>{{ __('Please correct the errors below.') }}</p>
            </template>
        </div>
    </div>

    {{-- Step panels --}}
    @foreach($steps as $i => $step)
        <div x-show="step === {{ $i }}" x-cloak class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body">
                @if(!empty($step['label']))
                    <h3 class="card-title text-base">{{ $step['label'] }}</h3>
                @endif
                @if(!empty($step['description']))
                    <p class="text-sm text-base-content/60 mb-2">{{ $step['description'] }}</p>
                @endif

                <div class="grid grid-cols-12 gap-4">
                    @include('daisyblade::form.fields', [
                        'fields'      => $step['schema'] ?? [],
                        'mode'        => 'alpine',
                        'alpineData'  => 'data',
                        'alphaErrors' => 'errors',
                    ])
                </div>
            </div>
        </div>
    @endforeach

    {{-- Navigation --}}
    <div class="flex items-center justify-between">
        <button
            type="button"
            @click="prev()"
            :disabled="step === 0"
            class="btn btn-ghost btn-sm"
        >
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            {{ __('Back') }}
        </button>

        <span class="text-sm text-base-content/50" x-text="`${step + 1} / {{ $stepCount }}`"></span>

        {{-- Next (not last step) --}}
        <button
            type="button"
            x-show="step < {{ $stepCount - 1 }}"
            @click="next()"
            :disabled="loading"
            class="btn btn-primary btn-sm"
        >
            <span x-show="loading" class="loading loading-spinner loading-xs" x-cloak></span>
            {{ __('Next') }}
            <x-heroicon-o-arrow-right class="w-4 h-4" />
        </button>

        {{-- Submit (last step) --}}
        <button
            type="button"
            x-show="step === {{ $stepCount - 1 }}"
            x-cloak
            @click="submit()"
            :disabled="loading"
            class="btn btn-success btn-sm"
        >
            <span x-show="loading" class="loading loading-spinner loading-xs" x-cloak></span>
            <x-heroicon-o-check class="w-4 h-4" />
            {{ $submitLabel ?: __('Finish') }}
        </button>
    </div>
</div>
