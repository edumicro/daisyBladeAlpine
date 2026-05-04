@props([
    'items'          => [],
    'orientation'    => 'vertical',
    'variant'        => 'default',
    'showConnector'  => true,
    'compact'        => false,
    'label'          => '',
    'class'          => '',
    'containerClass' => '',
])

@php
$colorMap = ['primary'=>'bg-primary','secondary'=>'bg-secondary','accent'=>'bg-accent','success'=>'bg-success','warning'=>'bg-warning','error'=>'bg-error','info'=>'bg-info','default'=>'bg-primary'];
@endphp

<div {{ $attributes->merge(['class' => "timeline " . ($orientation === 'horizontal' ? 'timeline-horizontal' : 'timeline-vertical') . " {$containerClass}"]) }}>
    @if($label)
        <div class="mb-6 pb-4 border-b border-base-300">
            <h3 class="text-lg font-bold">{{ $label }}</h3>
        </div>
    @endif

    @forelse($items as $item)
        <div class="timeline-item">
            <div class="{{ $compact ? 'text-xs' : 'text-sm' }} timeline-start text-right">
                @if(isset($item['date'])) <time class="font-semibold">{{ $item['date'] }}</time> @endif
                @if(isset($item['time'])) <div class="text-base-content/70">{{ $item['time'] }}</div> @endif
            </div>

            <div class="timeline-middle">
                @php $dotColor = $colorMap[$item['variant'] ?? $variant] ?? $colorMap['default']; @endphp
                <div class="w-4 h-4 rounded-full {{ $dotColor }} ring-4 ring-base-100 flex items-center justify-center {{ $class }}">
                    @if(isset($item['icon']))
                        <x-dynamic-component :component="$item['icon']" class="w-2 h-2 text-white" />
                    @else
                        <div class="w-2 h-2 bg-white rounded-full"></div>
                    @endif
                </div>
            </div>

            <div class="timeline-end {{ $compact ? 'mb-4' : 'mb-10' }} ml-4">
                @if(isset($item['title'])) <h3 class="font-bold {{ $compact ? 'text-sm' : '' }}">{{ $item['title'] }}</h3> @endif
                @if(isset($item['description'])) <p class="text-base-content/70 {{ $compact ? 'text-xs' : 'text-sm' }} mt-1">{{ $item['description'] }}</p> @endif
                @if(isset($item['content'])) <div class="prose prose-sm mt-2">{!! $item['content'] !!}</div> @endif

                @if(!empty($item['tags']))
                    <div class="flex gap-2 flex-wrap mt-3">
                        @foreach($item['tags'] as $tag)
                            <span class="badge badge-sm {{ isset($tag['variant']) ? 'badge-'.$tag['variant'] : 'badge-ghost' }}">{{ $tag['label'] ?? $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($showConnector && !$loop->last && $orientation === 'vertical')
                <hr />
            @endif
        </div>
    @empty
        <div class="text-center py-8 text-base-content/50">{{ __('No items') }}</div>
    @endforelse
</div>
