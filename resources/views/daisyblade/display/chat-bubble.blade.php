@props([
    'message'   => '',
    'label'     => '',
    'sender'    => '',
    'timestamp' => '',
    'avatar'    => '',
    'variant'   => 'sent',   // sent | received
    'loading'   => false,
    'actions'   => [],
    'icon'      => '',
    'class'     => '',
])

<div {{ $attributes->merge(['class' => 'chat ' . ($variant === 'sent' ? 'chat-end' : 'chat-start')]) }}>
    @if($avatar)
        <div class="chat-image avatar">
            <div class="w-10 h-10 rounded-full">
                <img src="{{ $avatar }}" alt="{{ $sender }}" />
            </div>
        </div>
    @endif

    @if($sender || $timestamp)
        <div class="chat-header">
            {{ $sender }}
            @if($timestamp)
                <time class="text-xs opacity-50 ml-2">{{ $timestamp }}</time>
            @endif
        </div>
    @endif

    <div class="chat-bubble {{ $variant === 'sent' ? 'chat-bubble-primary' : 'chat-bubble-neutral' }} {{ $class }}">
        @if($loading)
            <span class="loading loading-spinner loading-sm"></span>
            {{ __('Typing') }}...
        @else
            <div class="flex items-start gap-2">
                @if($icon)
                    <x-dynamic-component :component="$icon" class="w-4 h-4 shrink-0 mt-0.5" />
                @endif
                <div>{!! $message ?: $label !!}{{ $slot }}</div>
            </div>
        @endif
    </div>

    @if(!empty($actions) && !$loading)
        <div class="chat-footer text-xs text-base-content/70 mt-1 flex gap-2">
            @foreach($actions as $action)
                <a href="{{ $action['url'] ?? '#' }}" class="link link-hover text-xs">{{ $action['label'] ?? '' }}</a>
            @endforeach
        </div>
    @endif
</div>
