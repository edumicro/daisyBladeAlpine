@props([
    'meta'          => [],   // ['current_page'=>1,'last_page'=>5,'from'=>1,'to'=>15,'total'=>50,'per_page'=>15]
    'baseUrl'       => '',
    'showFirstLast' => true,
    'showPrevNext'  => true,
    'window'        => 2,
    'joinClass'     => '',
])

@php
$current    = (int)($meta['current_page'] ?? 1);
$last       = (int)($meta['last_page'] ?? 1);
$total      = (int)($meta['total'] ?? 0);
$from       = (int)($meta['from'] ?? (($current - 1) * ($meta['per_page'] ?? 15) + 1));
$to         = (int)($meta['to'] ?? min($current * ($meta['per_page'] ?? 15), $total));

// The window of pages around the current one, and the first and last page when the window does not reach them. "..." stands for
// the pages that are left out: only when there are two or more (for a single page, the window shows it).
$start = max(1, $current - $window);
$end   = min($last, $current + $window);

$pages = [];
if ($showFirstLast && $start > 1) {
    $pages[] = 1;
    if ($start > 2) $pages[] = '...';
}
for ($i = $start; $i <= $end; $i++) $pages[] = $i;
if ($showFirstLast && $end < $last) {
    if ($end < $last - 1) $pages[] = '...';
    $pages[] = $last;
}

$pageUrl = fn(int $p) => $baseUrl ? ($baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'page=' . $p) : '#';
@endphp

{{-- The root is the caller's element: it holds no Alpine attribute of ours. Each button that `$dispatch`es carries its own `x-data`,
     so the order works outside any other scope (layout.app has none) without a scope around the root. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-4']) }}>
    @if($total > 0)
        <div class="text-sm text-base-content/70 text-center">
            {{ __('Showing') }}
            <span class="font-semibold">{{ $from }}</span>–<span class="font-semibold">{{ $to }}</span>
            {{ __('of') }} <span class="font-semibold">{{ $total }}</span>
        </div>
    @endif

    <div class="join justify-center {{ $joinClass }}">
        @if($showPrevNext)
            @if($baseUrl)
                <a href="{{ $pageUrl(max(1, $current - 1)) }}" class="join-item btn btn-sm {{ $current <= 1 ? 'btn-disabled' : '' }}">‹ {{ __('Previous') }}</a>
            @else
                <button type="button" x-data @click="$dispatch('dbl-pagination-page-changed', { page: {{ max(1, $current - 1) }} })" @disabled($current <= 1) class="join-item btn btn-sm">‹ {{ __('Previous') }}</button>
            @endif
        @endif

        @foreach($pages as $page)
            @if($page === '...')
                <button disabled class="join-item btn btn-sm btn-disabled">…</button>
            @else
                @if($baseUrl)
                    <a href="{{ $pageUrl($page) }}" class="join-item btn btn-sm {{ $page === $current ? 'btn-active' : '' }}">{{ $page }}</a>
                @else
                    <button type="button" x-data @click="$dispatch('dbl-pagination-page-changed', { page: {{ $page }} })" class="join-item btn btn-sm {{ $page === $current ? 'btn-active' : '' }}">{{ $page }}</button>
                @endif
            @endif
        @endforeach

        @if($showPrevNext)
            @if($baseUrl)
                <a href="{{ $pageUrl(min($last, $current + 1)) }}" class="join-item btn btn-sm {{ $current >= $last ? 'btn-disabled' : '' }}">{{ __('Next') }} ›</a>
            @else
                <button type="button" x-data @click="$dispatch('dbl-pagination-page-changed', { page: {{ min($last, $current + 1) }} })" @disabled($current >= $last) class="join-item btn btn-sm">{{ __('Next') }} ›</button>
            @endif
        @endif
    </div>
</div>
