@props([
    'nodes'          => [],   // [{id, parent, label, status, events:[{name,action_type,termination_type,t_s,step_n,acc_value,...}], meta:{k:v,...}}]
    'label'          => '',
    'class'          => '',
    'containerClass' => '',
])

@php
// ── Color maps ────────────────────────────────────────────────────────────────
$termColors = [
    'end'   => ['badge' => 'badge-success', 'dot' => 'bg-success'],
    'abort' => ['badge' => 'badge-warning', 'dot' => 'bg-warning'],
    'fail'  => ['badge' => 'badge-error',   'dot' => 'bg-error'],
];
$defaultEvColor = ['badge' => 'badge-ghost', 'dot' => 'bg-base-300'];

// ── Build adjacency map ───────────────────────────────────────────────────────
$byParent = collect($nodes)->groupBy(fn($n) => $n['parent'] ?? '__root__');
$roots    = $byParent->get('__root__', collect())->all();

// ── Encode data safely for data-* attribute + Alpine JSON.parse ───────────────
$enc = fn(mixed $data): string =>
    htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

// ── Recursive node renderer ───────────────────────────────────────────────────
$renderNode = null;
$renderNode = function(array $node, int $depth = 0) use (&$renderNode, $byParent, $termColors, $defaultEvColor, $enc): string {
    $isRoot    = ($node['parent'] ?? null) === null;
    $dotCls    = $isRoot ? 'bg-primary'   : 'bg-secondary';
    $badgeCls  = $isRoot ? 'badge-primary' : 'badge-secondary';
    $events    = $node['events'] ?? [];
    $children  = $byParent->get($node['id'], collect())->all();
    $hasKids   = !empty($children);
    $indent    = $depth > 0 ? 'ml-8 pl-4 border-l-2 border-base-300' : '';
    $label     = htmlspecialchars($node['label'] ?? ('Nodo ' . $node['id']), ENT_QUOTES, 'UTF-8');
    $status    = $node['status'] ?? null;
    $statusCls = match($status) {
        'completed' => 'badge-success',
        'failed'    => 'badge-error',
        default     => 'badge-ghost',
    };
    $nodeItem  = $enc(['type' => 'node', 'data' => $node]);

    $html  = "<div class=\"{$indent} mt-3 first:mt-0\">";
    $html .= "<div class=\"flex items-start gap-3\">";

    // ── Dot + vertical connector ──────────────────────────────────────────────
    $html .= "<div class=\"flex flex-col items-center shrink-0 pt-0.5\">";
    $html .= "<button"
           . " type=\"button\""
           . " @click=\"selected = JSON.parse(\$el.dataset.item); \$refs.modal.showModal()\""
           . " data-item=\"{$nodeItem}\""
           . " class=\"w-5 h-5 rounded-full {$dotCls} ring-4 ring-base-100 hover:scale-110 transition-transform cursor-pointer shrink-0\""
           . " title=\"{$label}\"></button>";
    if ($hasKids) {
        $html .= "<div class=\"w-0.5 bg-base-300 grow mt-1 min-h-8\"></div>";
    }
    $html .= "</div>";

    // ── Node card ─────────────────────────────────────────────────────────────
    $html .= "<div class=\"flex-1 pb-2\">";

    $html .= "<div class=\"flex flex-wrap items-center gap-1.5 mb-2\">";
    $html .= "<span class=\"font-semibold text-sm\">{$label}</span>";
    $html .= "<span class=\"badge badge-xs {$badgeCls}\">" . ($isRoot ? 'raíz' : 'hijo') . "</span>";
    if ($status) {
        $html .= "<span class=\"badge badge-xs {$statusCls}\">{$status}</span>";
    }
    $html .= "</div>";

    // ── Event badges ──────────────────────────────────────────────────────────
    if (!empty($events)) {
        $html .= "<div class=\"flex flex-wrap gap-1.5\">";
        foreach ($events as $ev) {
            $term   = $ev['termination_type'] ?? null;
            $ec     = $termColors[$term] ?? $defaultEvColor;
            $evItem = $enc(['type' => 'event', 'data' => $ev]);
            $evName = htmlspecialchars($ev['name'] ?? $ev['action_type'] ?? '?', ENT_QUOTES, 'UTF-8');
            $isAcc  = in_array($ev['action_type'] ?? '', ['accumulator_end', 'accumulator_abort', 'accumulator_fail']);
            $accBit = ($isAcc && isset($ev['acc_value']))
                ? '<span class="opacity-50 font-mono text-xs ml-0.5">' . htmlspecialchars($ev['acc_value'], ENT_QUOTES, 'UTF-8') . '</span>'
                : '';

            $html .= "<button"
                   . " type=\"button\""
                   . " @click=\"selected = JSON.parse(\$el.dataset.item); \$refs.modal.showModal()\""
                   . " data-item=\"{$evItem}\""
                   . " class=\"badge badge-sm {$ec['badge']} gap-1 cursor-pointer hover:opacity-80 transition-opacity\">"
                   . "<span class=\"w-1.5 h-1.5 rounded-full {$ec['dot']} shrink-0\"></span>"
                   . $evName . $accBit
                   . "</button>";
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
    x-data="{ selected: null }"
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

    {{-- ── Detail modal (shared, Alpine-driven) ────────────────────────────── --}}
    <dialog x-ref="modal" class="modal" @click.self="$refs.modal.close()">
        <div class="modal-box max-w-sm" @click.stop>
            <button
                type="button"
                class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                @click="$refs.modal.close()"
            >✕</button>

            {{-- Node detail --}}
            <template x-if="selected && selected.type === 'node'">
                <div>
                    <h3 class="font-bold text-lg mb-4 flex items-center gap-2 pr-8">
                        <span
                            class="badge shrink-0"
                            :class="selected.data.parent == null ? 'badge-primary' : 'badge-secondary'"
                            x-text="selected.data.parent == null ? 'raíz' : 'hijo'"
                        ></span>
                        <span x-text="selected.data.label ?? ('Nodo ' + selected.data.id)"></span>
                    </h3>
                    <div class="divide-y divide-base-200 text-sm">
                        <div class="flex justify-between py-1.5">
                            <span class="text-base-content/60">ID local</span>
                            <span x-text="selected.data.id"></span>
                        </div>
                        <div class="flex justify-between py-1.5">
                            <span class="text-base-content/60">Padre</span>
                            <span x-text="selected.data.parent ?? '—'"></span>
                        </div>
                        <div class="flex justify-between py-1.5">
                            <span class="text-base-content/60">Estado</span>
                            <span x-text="selected.data.status ?? '—'"></span>
                        </div>
                        <template x-for="[k, v] in Object.entries(selected.data.meta ?? {})">
                            <div class="flex justify-between py-1.5">
                                <span class="text-base-content/60 font-mono text-xs" x-text="k"></span>
                                <span x-text="v"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Event detail --}}
            <template x-if="selected && selected.type === 'event'">
                <div>
                    <h3 class="font-bold text-lg mb-4 flex items-center gap-2 pr-8">
                        <span
                            class="badge badge-sm shrink-0"
                            :class="{
                                'badge-success': selected.data.termination_type === 'end',
                                'badge-warning': selected.data.termination_type === 'abort',
                                'badge-error':   selected.data.termination_type === 'fail',
                                'badge-ghost':   !selected.data.termination_type
                            }"
                            x-text="selected.data.termination_type ?? 'evento'"
                        ></span>
                        <span x-text="selected.data.name ?? selected.data.action_type"></span>
                    </h3>
                    <div class="divide-y divide-base-200 text-sm">
                        <div class="flex justify-between py-1.5">
                            <span class="text-base-content/60">action_type</span>
                            <span class="font-mono text-xs" x-text="selected.data.action_type ?? '—'"></span>
                        </div>
                        <div class="flex justify-between py-1.5">
                            <span class="text-base-content/60">t_s</span>
                            <span x-text="selected.data.t_s ?? '—'"></span>
                        </div>
                        <div class="flex justify-between py-1.5">
                            <span class="text-base-content/60">step_n</span>
                            <span x-text="selected.data.step_n ?? '—'"></span>
                        </div>
                        <template x-if="selected.data.acc_value != null">
                            <div class="flex justify-between py-1.5 font-medium">
                                <span class="text-base-content/60">acc_value</span>
                                <span x-text="selected.data.acc_value"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
        <form method="dialog" class="modal-backdrop"><button>cerrar</button></form>
    </dialog>
</div>
