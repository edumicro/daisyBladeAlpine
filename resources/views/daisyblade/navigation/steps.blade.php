@props([
    'steps'            => [],
    'currentStep'      => 1,
    'showDescriptions' => true,
    'clickable'        => false,
    'vertical'         => false,
    'color'            => 'primary',
    'stepsClass'       => '',
])

@php
$stepCount = count($steps);
// Class names written out in full, not glued together from a prefix and the color: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$stepColorClass = match($color) {
    'neutral' => 'step-neutral',
    'primary' => 'step-primary',
    'secondary' => 'step-secondary',
    'accent' => 'step-accent',
    'info' => 'step-info',
    'success' => 'step-success',
    'warning' => 'step-warning',
    'error' => 'step-error',
    default => 'step-primary',
};
@endphp

<div
    x-data="{
        current: {{ $currentStep }},
        total: {{ $stepCount }},
        prev() { if (this.current > 1) { this.current--; this.$dispatch('dbl-steps-step-changed', { step: this.current }); } },
        next() { if (this.current < this.total) { this.current++; this.$dispatch('dbl-steps-step-changed', { step: this.current }); } },
    }"
    {{ $attributes }}
>
    <ul class="steps {{ $vertical ? 'steps-vertical' : '' }} w-full {{ $stepsClass }}">
        @foreach($steps as $index => $step)
            @php $num = $index + 1; @endphp
            <li
                :data-content="current > {{ $num }} ? '✓' : '{{ $num }}'"
                :class="{
                    'step-success': current > {{ $num }},
                    '{{ $stepColorClass }}': current === {{ $num }}
                }"
                class="step {{ $clickable ? 'cursor-pointer' : '' }}"
                @if($clickable) @click="current = {{ $num }}; $dispatch('dbl-steps-step-changed', { step: {{ $num }} })" @endif
            >
                <div class="flex flex-col gap-1">
                    <span class="font-medium text-sm">{{ $step['label'] ?? '' }}</span>
                    @if($showDescriptions && ($step['description'] ?? false))
                        <span class="text-xs text-base-content/70">{{ $step['description'] }}</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    <div class="mt-6">{{ $slot }}</div>

    @if($stepCount > 1)
        <div class="flex justify-between gap-3 mt-6">
            <button
                type="button"
                @click="prev()"
                :disabled="current === 1"
                class="btn btn-outline btn-sm"
            >{{ __('Back') }}</button>

            <span
                x-text="`{{ __('Step') }} ${current} / {{ $stepCount }}`"
                class="text-sm text-base-content/70 flex items-center"
            ></span>

            <button
                type="button"
                @click="next()"
                :disabled="current === total"
                class="btn btn-primary btn-sm"
            >
                <span x-show="current < total" x-cloak>{{ __('Next') }}</span>
                <span x-show="current === total">{{ __('Finish') }}</span>
            </button>
        </div>
    @endif
</div>
