@props([
    'currentPage'   => 1,
    'total'         => 0,
    'perPage'       => 15,
    'window'        => 2,
    'showFirstLast' => true,
    'showPrevNext'  => true,
    'queryParam'    => 'page',
    'url'           => '',
    'class'         => '',
    'containerClass' => '',
])

@php
$totalPages = (int)ceil($total / max(1, $perPage)) ?: 1;

$pages = [];
if ($showFirstLast && $currentPage > ($window + 2)) {
    $pages[] = 1;
    if ($currentPage > ($window + 3)) $pages[] = '...';
}
$start = max(1, $currentPage - $window);
$end   = min($totalPages, $currentPage + $window);
for ($i = $start; $i <= $end; $i++) $pages[] = $i;
if ($showFirstLast && $currentPage < ($totalPages - $window - 1)) {
    if ($currentPage < ($totalPages - $window - 2)) $pages[] = '...';
    $pages[] = $totalPages;
}

$startItem = ($currentPage - 1) * $perPage + 1;
$endItem   = min($currentPage * $perPage, $total);

$pageUrl = fn(int $p) => $url ? ($url . (str_contains($url, '?') ? '&' : '?') . $queryParam . '=' . $p) : '#';
@endphp

<div {{ $attributes->merge(['class' => "flex flex-col gap-4 {$containerClass}"]) }}>
    @if($total > 0)
        <div class="text-sm text-base-content/70 text-center">
            {{ __('Showing') }}
            <span class="font-semibold">{{ $startItem }}</span>
            {{ __('to') }}
            <span class="font-semibold">{{ $endItem }}</span>
            {{ __('of') }}
            <span class="font-semibold">{{ $total }}</span>
            {{ __('items') }}
        </div>
    @endif

    <div class="join justify-center {{ $class }}">
        @if($showPrevNext)
            @php $prevPage = max(1, $currentPage - 1); @endphp
            @if($url)
                <a href="{{ $pageUrl($prevPage) }}" class="join-item btn btn-sm {{ $currentPage === 1 ? 'btn-disabled' : '' }}">{{ __('Previous') }}</a>
            @else
                <button type="button" @click="$dispatch('page-changed', { page: {{ $prevPage }} })" @disabled($currentPage === 1) class="join-item btn btn-sm">{{ __('Previous') }}</button>
            @endif
        @endif

        @foreach($pages as $page)
            @if($page === '...')
                <button disabled class="join-item btn btn-sm btn-disabled">...</button>
            @else
                @if($url)
                    <a href="{{ $pageUrl($page) }}" class="join-item btn btn-sm {{ $page === $currentPage ? 'btn-active' : '' }}">{{ $page }}</a>
                @else
                    <button type="button" @click="$dispatch('page-changed', { page: {{ $page }} })" class="join-item btn btn-sm {{ $page === $currentPage ? 'btn-active' : '' }}">{{ $page }}</button>
                @endif
            @endif
        @endforeach

        @if($showPrevNext)
            @php $nextPage = min($totalPages, $currentPage + 1); @endphp
            @if($url)
                <a href="{{ $pageUrl($nextPage) }}" class="join-item btn btn-sm {{ $currentPage === $totalPages ? 'btn-disabled' : '' }}">{{ __('Next') }}</a>
            @else
                <button type="button" @click="$dispatch('page-changed', { page: {{ $nextPage }} })" @disabled($currentPage === $totalPages) class="join-item btn btn-sm">{{ __('Next') }}</button>
            @endif
        @endif
    </div>

    @if($totalPages > 1)
        <div class="flex justify-center items-center gap-2">
            <label class="text-sm text-base-content/70">{{ __('Go to page') }}:</label>
            @if($url)
                <select onchange="window.location.href='{{ $url }}' + ({{ json_encode(str_contains($url, '?')) }} ? '&' : '?') + '{{ $queryParam }}=' + this.value" class="select select-sm select-bordered w-20">
                    @for($i = 1; $i <= $totalPages; $i++)
                        <option value="{{ $i }}" {{ $i === $currentPage ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
            @else
                <select @change="$dispatch('page-changed', { page: parseInt($event.target.value) })" class="select select-sm select-bordered w-20">
                    @for($i = 1; $i <= $totalPages; $i++)
                        <option value="{{ $i }}" {{ $i === $currentPage ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
            @endif
        </div>
    @endif
</div>
