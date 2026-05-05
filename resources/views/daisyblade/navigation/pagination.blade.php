@props([
    'meta'          => [],   // ['current_page'=>1,'last_page'=>5,'from'=>1,'to'=>15,'total'=>50,'per_page'=>15]
    'baseUrl'       => '',
    'showFirstLast' => true,
    'showPrevNext'  => true,
    'window'        => 2,
    'class'         => '',
    'containerClass' => '',
])

@php
$current    = (int)($meta['current_page'] ?? 1);
$last       = (int)($meta['last_page'] ?? 1);
$total      = (int)($meta['total'] ?? 0);
$from       = (int)($meta['from'] ?? (($current - 1) * ($meta['per_page'] ?? 15) + 1));
$to         = (int)($meta['to'] ?? min($current * ($meta['per_page'] ?? 15), $total));

$pages = [];
if ($showFirstLast && $current > ($window + 2)) {
    $pages[] = 1;
    if ($current > ($window + 3)) $pages[] = '...';
}
$start = max(1, $current - $window);
$end   = min($last, $current + $window);
for ($i = $start; $i <= $end; $i++) $pages[] = $i;
if ($showFirstLast && $current < ($last - $window - 1)) {
    if ($current < ($last - $window - 2)) $pages[] = '...';
    $pages[] = $last;
}

$pageUrl = fn(int $p) => $baseUrl ? ($baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'page=' . $p) : '#';
@endphp

<div {{ $attributes->merge(['class' => "flex flex-col gap-4 {$containerClass}"]) }}>
    @if($total > 0)
        <div class="text-sm text-base-content/70 text-center">
            {{ __('Showing') }}
            <span class="font-semibold">{{ $from }}</span>–<span class="font-semibold">{{ $to }}</span>
            {{ __('of') }} <span class="font-semibold">{{ $total }}</span>
        </div>
    @endif

    <div class="join justify-center {{ $class }}">
        @if($showPrevNext)
            @if($baseUrl)
                <a href="{{ $pageUrl(max(1, $current - 1)) }}" class="join-item btn btn-sm {{ $current <= 1 ? 'btn-disabled' : '' }}">‹ {{ __('Previous') }}</a>
            @else
                <button type="button" @click="$dispatch('page-changed', { page: {{ max(1, $current - 1) }} })" @disabled($current <= 1) class="join-item btn btn-sm">‹ {{ __('Previous') }}</button>
            @endif
        @endif

        @foreach($pages as $page)
            @if($page === '...')
                <button disabled class="join-item btn btn-sm btn-disabled">…</button>
            @else
                @if($baseUrl)
                    <a href="{{ $pageUrl($page) }}" class="join-item btn btn-sm {{ $page === $current ? 'btn-active' : '' }}">{{ $page }}</a>
                @else
                    <button type="button" @click="$dispatch('page-changed', { page: {{ $page }} })" class="join-item btn btn-sm {{ $page === $current ? 'btn-active' : '' }}">{{ $page }}</button>
                @endif
            @endif
        @endforeach

        @if($showPrevNext)
            @if($baseUrl)
                <a href="{{ $pageUrl(min($last, $current + 1)) }}" class="join-item btn btn-sm {{ $current >= $last ? 'btn-disabled' : '' }}">{{ __('Next') }} ›</a>
            @else
                <button type="button" @click="$dispatch('page-changed', { page: {{ min($last, $current + 1) }} })" @disabled($current >= $last) class="join-item btn btn-sm">{{ __('Next') }} ›</button>
            @endif
        @endif
    </div>
</div>
