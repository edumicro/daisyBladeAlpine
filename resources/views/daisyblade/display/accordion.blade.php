@props([
    'items'       => [],
    'id'          => 'acc',
    'icon'        => '',
    'iconSide'    => 'right',
    'class'       => '',
    'containerClass' => '',
    'label'       => '',
])

<div class="join join-vertical w-full {{ $containerClass }}">
    @if($label)
        <p class="font-semibold mb-2">{{ $label }}</p>
    @endif

    @forelse($items as $index => $item)
        <div class="collapse collapse-arrow join-item border border-base-300">
            <input type="radio" name="accordion-{{ $id }}" {{ ($item['open'] ?? $index === 0) ? 'checked' : '' }} />

            <div class="collapse-title flex items-center gap-2 font-medium {{ $class }}">
                @if($icon && $iconSide === 'left')
                    <x-dynamic-component :component="$icon" class="w-5 h-5 shrink-0" />
                @endif
                <span>{{ $item['title'] ?? '' }}</span>
                @if($icon && $iconSide === 'right')
                    <x-dynamic-component :component="$icon" class="w-5 h-5 shrink-0 ml-auto" />
                @endif
            </div>

            <div class="collapse-content text-sm">
                <div class="py-2">
                    @if(isset($item['content'])) {!! $item['content'] !!} @endif
                </div>
            </div>
        </div>
    @empty
        <div class="alert alert-info">{{ __('No items') }}</div>
    @endforelse
</div>
