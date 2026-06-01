@props([
    'items'          => [],
    'label'          => '',
    'expandAll'      => false,
    'class'          => '',
    'containerClass' => '',
])

@php
$expandAll = (bool) $expandAll;

$renderTree = null;
$renderTree = function (array $items, int $depth = 0) use (&$renderTree, $expandAll): string {
    if (empty($items)) {
        return '';
    }

    $indent = $depth > 0 ? 'ml-4 mt-0.5 border-l border-base-300/50 pl-3' : '';
    $html   = '<ul class="space-y-0.5 ' . $indent . '">';

    foreach ($items as $key => $value) {
        $isBranch    = is_array($value) && !empty($value);
        $nodeLabel   = is_int($key) ? (string) $value : (string) $key;
        $description = (!is_int($key) && is_string($value) && $value !== '') ? $value : null;

        if ($isBranch) {
            $open  = $expandAll ? ' open' : '';
            $html .= '<li>';
            $html .= '<details' . $open . '>';
            $html .= '<summary class="flex items-center gap-1.5 text-sm cursor-pointer font-medium select-none hover:text-primary transition-colors py-0.5">';
            $html .= '<span class="text-base-content/40 text-xs">▸</span>';
            $html .= e($nodeLabel);
            $html .= '</summary>';
            $html .= $renderTree($value, $depth + 1);
            $html .= '</details></li>';
        } else {
            $html .= '<li class="flex items-baseline gap-2 text-sm py-0.5">';
            $html .= '<span class="text-base-content/30 shrink-0 select-none">—</span>';
            $html .= '<span>' . e($nodeLabel);
            if ($description !== null) {
                $html .= ' <span class="text-xs text-base-content/50">' . e($description) . '</span>';
            }
            $html .= '</span></li>';
        }
    }

    $html .= '</ul>';
    return $html;
};
@endphp

<div {{ $attributes->merge(['class' => $containerClass]) }}>
    @if($label)
        <div class="mb-3 pb-2 border-b border-base-300">
            <h3 class="font-semibold">{{ $label }}</h3>
        </div>
    @endif

    <div class="{{ $class }}">
        {!! $renderTree($items) !!}
    </div>
</div>
