@props([
    'nodes'          => [],   // [{id, parent, label, status, badges:[{label,variant,flat}], flat}]
    'editable'       => false,
    'label'          => '',
    'class'          => '',
    'containerClass' => '',
])

{{--
  Eventos emitidos (siempre):
    @node-click   { id, flat }                         — clic en un nodo
    @badge-click  { nodeId, badgeFlat }                — clic en un badge

  Eventos emitidos solo cuando editable=true:
    @node-create  { parentId }                         — botón + en un nodo; parentId = id del nodo clicado
    @node-delete  { id, flat }                         — eliminar nodo (solo nodos no-raíz)
    @badge-create { nodeId }                           — crear badge en un nodo
    @badge-delete { nodeId, badgeFlat }                — eliminar badge
    @edge-create  { fromId, toId, fromFlat, toFlat }   — arrastrar dot de fromId y soltar sobre toId

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

    // ── Editable: drop-zone on flex-row (common ancestor of dot + card) ───────
    //    The flex-row naturally isolates from sibling/child nodes because child
    //    outer-divs are siblings of the flex-row, not descendants of it.
    $flexRowDrop = $editable
        ? " data-node-id=\"{$nodeId}\" data-flat=\"{$nodeFlat}\""
          . " @dragover.prevent=\"\$el.style.boxShadow='0 0 0 2px oklch(var(--p,65% 0.3 250)/.6)'\""
          . " @dragleave=\"if(!\$el.contains(\$event.relatedTarget))\$el.style.boxShadow=''\""
          . " @drop.prevent=\"const f=\$event.dataTransfer.getData('ng-node');const t=\$el.dataset.nodeId;\$el.style.boxShadow='';if(f&&f!==t){\$dispatch('edge-create',{fromId:JSON.parse(f),toId:JSON.parse(t),fromFlat:JSON.parse(\$event.dataTransfer.getData('ng-flat')||'null'),toFlat:JSON.parse(\$el.dataset.flat)})}\""
        : '';

    // ── Editable: drag-source on dot button ───────────────────────────────────
    $dotDragAttrs = $editable
        ? " draggable=\"true\""
          . " @dragstart=\"\$event.dataTransfer.setData('ng-node',\$el.dataset.nodeId);\$event.dataTransfer.setData('ng-flat',\$el.dataset.flat);\$event.dataTransfer.effectAllowed='link'\""
        : '';
    $dotCursor = $editable ? 'cursor-grab' : 'cursor-pointer';

    $html  = "<div class=\"{$indent} mt-3 first:mt-0\">";
    $html .= "<div class=\"flex items-start gap-3\"{$flexRowDrop}>";

    // ── Dot + vertical connector ──────────────────────────────────────────────
    $html .= "<div class=\"flex flex-col items-center shrink-0 pt-0.5\">";
    $html .= "<button"
           . " type=\"button\""
           . " data-node-id=\"{$nodeId}\""
           . " data-flat=\"{$nodeFlat}\""
           . $dotDragAttrs
           . " @click=\"\$dispatch('node-click', { id: JSON.parse(\$el.dataset.nodeId), flat: JSON.parse(\$el.dataset.flat) })\""
           . " class=\"w-5 h-5 rounded-full {$dotCls} ring-4 ring-base-100 hover:scale-110 transition-transform {$dotCursor} shrink-0\""
           . " title=\"{$label}" . ($editable ? " — arrastra para enlazar" : "") . "\"></button>";
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
               . " @click.stop=\"\$dispatch('node-create', { parentId: JSON.parse(\$el.dataset.nodeId) })\""
               . " class=\"btn btn-xs btn-ghost px-1 opacity-50 hover:opacity-100\""
               . " title=\"Añadir hijo\">+</button>";
        // Delete node (not root)
        if (!$isRoot) {
            $html .= "<button"
                   . " type=\"button\""
                   . " data-node-id=\"{$nodeId}\""
                   . " data-flat=\"{$nodeFlat}\""
                   . " @click.stop=\"\$dispatch('node-delete', { id: JSON.parse(\$el.dataset.nodeId), flat: JSON.parse(\$el.dataset.flat) })\""
                   . " class=\"btn btn-xs btn-ghost px-1 opacity-50 hover:opacity-100 text-error\""
                   . " title=\"Eliminar nodo\">×</button>";
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
                       . " title=\"Eliminar badge\">×</button>";
            }
            $html .= "</span>";
        }
        if ($editable) {
            $html .= "<button"
                   . " type=\"button\""
                   . " data-node-id=\"{$nodeId}\""
                   . " @click.stop=\"\$dispatch('badge-create', { nodeId: JSON.parse(\$el.dataset.nodeId) })\""
                   . " class=\"badge badge-sm badge-ghost gap-1 cursor-pointer opacity-50 hover:opacity-100\""
                   . " title=\"Añadir badge\">+ badge</button>";
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
            <div class="text-center py-8 text-base-content/40 text-sm">Sin nodos</div>
        @endforelse
    </div>
</div>
