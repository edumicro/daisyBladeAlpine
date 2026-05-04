@props([
    'steps'            => [],
    'currentStep'      => 1,
    'showDescriptions' => true,
    'clickable'        => false,
    'vertical'         => false,
    'color'            => 'primary',
    'class'            => '',
    'containerClass'   => '',
])

@php $stepCount = count($steps); @endphp

<div
    x-data="{
        current: {{ $currentStep }},
        total: {{ $stepCount }},
        prev() { if (this.current > 1) { this.current--; this.$dispatch('step-changed', { step: this.current }); } },
        next() { if (this.current < this.total) { this.current++; this.$dispatch('step-changed', { step: this.current }); } },
    }"
    {{ $attributes->merge(['class' => $containerClass]) }}
>
    <ul class="steps {{ $vertical ? 'steps-vertical' : '' }} w-full {{ $class }}">
        @foreach($steps as $index => $step)
            @php $num = $index + 1; @endphp
            <li
                :data-content="current > {{ $num }} ? '✓' : '{{ $num }}'"
                :class="{
                    'step-success': current > {{ $num }},
                    'step-{{ $color }}': current === {{ $num }}
                }"
                class="step {{ $clickable ? 'cursor-pointer' : '' }}"
                @if($clickable) @click="current = {{ $num }}; $dispatch('step-changed', { step: {{ $num }} })" @endif
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
