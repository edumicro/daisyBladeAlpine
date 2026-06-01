@props([
    'rows'           => [],
    'leftLabel'      => '',
    'rightLabel'     => '',
    'label'          => '',
    'class'          => '',
    'containerClass' => '',
])

<div {{ $attributes->merge(['class' => $containerClass]) }}>
    @if($label)
        <div class="mb-3 pb-2 border-b border-base-300">
            <h3 class="font-semibold">{{ $label }}</h3>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="table table-zebra w-full {{ $class }}">
            <thead>
                <tr>
                    <th>{{ __('Parameter') }}</th>
                    <th>{{ $leftLabel ?: __('Left') }}</th>
                    <th>{{ $rightLabel ?: __('Right') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php $status = $row['status'] ?? 'equal'; @endphp
                    <tr @class([
                        'bg-warning/10' => $status === 'changed',
                        'bg-info/10'    => in_array($status, ['only_left', 'only_right']),
                    ])>
                        <td
                            class="font-medium"
                            @if(!empty($row['description'])) title="{{ $row['description'] }}" @endif
                        >
                            {{ $row['label'] ?? $row['key'] ?? '' }}
                        </td>
                        <td @class([
                            'font-bold'                   => $status === 'changed',
                            'text-base-content/40 italic' => $status === 'only_right',
                        ])>
                            {{ isset($row['left']) && $row['left'] !== null ? $row['left'] : '—' }}
                        </td>
                        <td @class([
                            'font-bold'                   => $status === 'changed',
                            'text-base-content/40 italic' => $status === 'only_left',
                        ])>
                            {{ isset($row['right']) && $row['right'] !== null ? $row['right'] : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-8 text-base-content/50">{{ __('No data') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
