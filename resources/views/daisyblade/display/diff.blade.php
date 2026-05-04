@props([
    'changes'         => [],
    'showLineNumbers' => true,
    'language'        => 'plaintext',
    'icon'            => 'heroicon-o-document-text',
    'label'           => '',
    'class'           => '',
    'containerClass'  => '',
])

<div {{ $attributes->merge(['class' => $containerClass]) }}>
    @if($label)
        <div class="flex items-center gap-2 mb-4 pb-4 border-b border-base-300">
            @if($icon)
                <x-dynamic-component :component="$icon" class="w-5 h-5" />
            @endif
            <h3 class="font-semibold">{{ $label }}</h3>
            <span class="text-xs text-base-content/50">{{ $language }}</span>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="table table-compact w-full">
            <tbody>
                @forelse($changes as $index => $change)
                    @php
                    $rowClass = match($change['type'] ?? 'normal') {
                        'added'    => 'bg-success/20 border-l-4 border-success',
                        'removed'  => 'bg-error/20 border-l-4 border-error',
                        'modified' => 'bg-warning/20 border-l-4 border-warning',
                        default    => 'bg-base-200/50 border-l-4 border-base-300',
                    };
                    $badge = match($change['type'] ?? 'normal') {
                        'added'    => ['+', 'badge-success'],
                        'removed'  => ['−', 'badge-error'],
                        'modified' => ['~', 'badge-warning'],
                        default    => [' ', 'badge-ghost'],
                    };
                    @endphp
                    <tr class="{{ $rowClass }} {{ $class }}">
                        @if($showLineNumbers)
                            <td class="w-12 text-center text-xs text-base-content/50 select-none font-mono">
                                {{ $change['lineNumber'] ?? $index + 1 }}
                            </td>
                        @endif
                        <td class="w-8 text-center select-none">
                            <span class="badge badge-sm {{ $badge[1] }}">{{ $badge[0] }}</span>
                        </td>
                        <td class="font-mono text-sm py-3">
                            <code class="whitespace-pre-wrap break-words">{{ $change['content'] ?? '' }}</code>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center py-8 text-base-content/50">{{ __('No changes') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
