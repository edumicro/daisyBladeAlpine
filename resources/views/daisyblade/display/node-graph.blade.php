@props([
    'nodes'          => [],   // [{id, parent, label, status, badges:[{label,variant,flat}], flat}]
    'editable'       => false,
    'label'          => '',
    'class'          => '',
    'containerClass' => '',
])

{{--
  Eventos emitidos (siempre):
    @node-click   { id, flat }                 — clic en un nodo
    @badge-click  { nodeId, badgeFlat }        — clic en un badge

  Eventos emitidos solo cuando editable=true:
    @node-create  { parentId }                 — crear hijo de un nodo
    @node-delete  { id, flat }                 — eliminar un nodo
    @badge-create { nodeId }                   — crear badge en un nodo
    @badge-delete { nodeId, badgeFlat }        — eliminar un badge

  El componente no sabe qué hacer con estos eventos — los emite y el padre decide.
--}}

@php
// ── Generic variant → CSS class map ──────────────────────────────────────────
$variantMap = [
    'success' => ['badge' => 'badge-success', 'dot' => 'bg-success'],
    'warning' => ['badge' => 'badge-warning', 'dot' => 'bg-warning'],
    'error'   => ['badge' => 'badge-error',   'dot' => 'bg-error'],
    'neutral' => ['badge' => 'badge-ghost',   'dot' => 'bg-base-300'],
];

// ── Build adjacency map ───────────────────────────────────────────────────────
$byParent = collect($nodes)->groupBy(fn($n) => $n['parent'] ?? '__root__');
$roots    = $byParent->get('__root__', collect())->all();

// ── Encode safely for data-* attributes (Alpine will JSON.parse) ──────────────
$enc = fn(mixed $data): string =>
    htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

// ── Recursive node renderer ───────────────────────────────────────────────────
$renderNode = null;
$renderNode = function(array $node, int $depth = 0) use (&$renderNode, $byParent, $variantMap, $enc, $editable): string {
    $isRoot   = ($node['parent'] ?? null) === null;
    $dotCls   = $isRoot ? 'bg-primary'   : 'bg-secondary';
    $ringCls  = $isRoot ? 'badge-primary' : 'badge-secondary';
    $badges   = $node['badges'] ?? [];
    $children = $byParent->get($node['id'], collect())->all();
    $hasKids  = !empty($children);
    $indent   = $depth > 0 ? 'ml-8 pl-4 border-l-2 border-base-300' : '';
    $label    = htmlspecialchars($node['label'] ?? ('Node ' . $node['id']), ENT_QUOTES, 'UTF-8');
    $status   = $node['status'] ?? null;
    $statusCls = match($status) {
        'completed' => 'badge-success',
        'failed'    => 'badge-error',
        default     => 'badge-ghost',
    };

    $nodeId   = $enc($node['id']);
    $nodeFlat = $enc($node['flat'] ?? null);

    $html  = "<div class=\"{$indent} mt-3 first:mt-0\">";
    $html .= "<div class=\"flex items-start gap-3\">";

    // ── Dot + vertical connector ──────────────────────────────────────────────
    $html .= "<div class=\"flex flex-col items-center shrink-0 pt-0.5\">";
    $html .= "<button"
           . " type=\"button\""
           . " data-node-id=\"{$nodeId}\""
           . " data-flat=\"{$nodeFlat}\""
           . " @click=\"\$dispatch('node-click', { id: JSON.parse(\$el.dataset.nodeId), flat: JSON.parse(\$el.dataset.flat) })\""
           . " class=\"w-5 h-5 rounded-full {$dotCls} ring-4 ring-base-100 hover:scale-110 transition-transform cursor-pointer shrink-0\""
           . " title=\"{$label}\"></button>";
    if ($hasKids) {
        $html .= "<div class=\"w-0.5 bg-base-300 grow mt-1 min-h-8\"></div>";
    }
    $html .= "</div>";

    // ── Node card ─────────────────────────────────────────────────────────────
    $html .= "<div class=\"flex-1 pb-2\">";

    // Header row: label + status + edit-mode controls
    $html .= "<div class=\"flex flex-wrap items-center gap-1.5 mb-2\">";
    $html .= "<span class=\"font-semibold text-sm\">{$label}</span>";
    $html .= "<span class=\"badge badge-xs {$ringCls}\">" . ($isRoot ? 'root' : 'child') . "</span>";
    if ($status) {
        $html .= "<span class=\"badge badge-xs {$statusCls}\">{$status}</span>";
    }
    if ($editable) {
        // Add child node
        $html .= "<button"
               . " type=\"button\""
               . " data-node-id=\"{$nodeId}\""
               . " @click=\"\$dispatch('node-create', { parentId: JSON.parse(\$el.dataset.nodeId) })\""
               . " class=\"btn btn-xs btn-ghost px-1 opacity-50 hover:opacity-100\""
               . " title=\"Add child node\">+</button>";
        // Delete node (not root)
        if (!$isRoot) {
            $html .= "<button"
                   . " type=\"button\""
                   . " data-node-id=\"{$nodeId}\""
                   . " data-flat=\"{$nodeFlat}\""
                   . " @click=\"\$dispatch('node-delete', { id: JSON.parse(\$el.dataset.nodeId), flat: JSON.parse(\$el.dataset.flat) })\""
                   . " class=\"btn btn-xs btn-ghost px-1 opacity-50 hover:opacity-100 text-error\""
                   . " title=\"Delete node\">×</button>";
        }
    }
    $html .= "</div>";

    // ── Badges ────────────────────────────────────────────────────────────────
    if (!empty($badges) || $editable) {
        $html .= "<div class=\"flex flex-wrap gap-1.5\">";
        foreach ($badges as $badge) {
            $bc        = $variantMap[$badge['variant'] ?? 'neutral'] ?? $variantMap['neutral'];
            $badgeFlat = $enc($badge['flat'] ?? null);
            $badgeLbl  = htmlspecialchars($badge['label'] ?? '?', ENT_QUOTES, 'UTF-8');

            $html .= "<span class=\"badge badge-sm {$bc['badge']} gap-1\">";
            $html .= "<button"
                   . " type=\"button\""
                   . " data-node-id=\"{$nodeId}\""
                   . " data-flat=\"{$badgeFlat}\""
                   . " @click=\"\$dispatch('badge-click', { nodeId: JSON.parse(\$el.dataset.nodeId), badgeFlat: JSON.parse(\$el.dataset.flat) })\""
                   . " class=\"flex items-center gap-1 cursor-pointer hover:opacity-80 transition-opacity\">"
                   . "<span class=\"w-1.5 h-1.5 rounded-full {$bc['dot']} shrink-0\"></span>"
                   . $badgeLbl
                   . "</button>";
            if ($editable) {
                $html .= "<button"
                       . " type=\"button\""
                       . " data-node-id=\"{$nodeId}\""
                       . " data-flat=\"{$badgeFlat}\""
                       . " @click.stop=\"\$dispatch('badge-delete', { nodeId: JSON.parse(\$el.dataset.nodeId), badgeFlat: JSON.parse(\$el.dataset.flat) })\""
                       . " class=\"opacity-50 hover:opacity-100 leading-none\""
                       . " title=\"Remove badge\">×</button>";
            }
            $html .= "</span>";
        }
        if ($editable) {
            $html .= "<button"
                   . " type=\"button\""
                   . " data-node-id=\"{$nodeId}\""
                   . " @click=\"\$dispatch('badge-create', { nodeId: JSON.parse(\$el.dataset.nodeId) })\""
                   . " class=\"badge badge-sm badge-ghost gap-1 cursor-pointer opacity-50 hover:opacity-100\""
                   . " title=\"Add badge\">+ badge</button>";
        }
        $html .= "</div>";
    }

    $html .= "</div>"; // card
    $html .= "</div>"; // flex items-start

    // ── Children ──────────────────────────────────────────────────────────────
    foreach ($children as $child) {
        $html .= $renderNode($child, $depth + 1);
    }

    $html .= "</div>";
    return $html;
};
@endphp

<div
    x-data="{}"
    {{ $attributes->merge(['class' => 'relative ' . $containerClass]) }}
>
    @if($label)
        <div class="mb-4 pb-3 border-b border-base-300">
            <h3 class="font-semibold text-base">{{ $label }}</h3>
        </div>
    @endif

    <div class="{{ $class }}">
        @forelse($roots as $root)
            {!! $renderNode($root) !!}
        @empty
            <div class="text-center py-8 text-base-content/40 text-sm">No nodes</div>
        @endforelse
    </div>
</div>
