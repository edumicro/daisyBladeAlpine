@props([
    'rows'     => [],
    'columns'  => [],
    'striped'  => false,
    'compact'  => false,
    'bordered' => false,
    'label'    => '',
    'class'    => '',
    'containerClass' => '',
])

<div class="overflow-x-auto w-full {{ $containerClass }}">
    @if($label)
        <h3 class="text-lg font-bold mb-4">{{ $label }}</h3>
    @endif

    <table {{ $attributes->merge(['class' => trim('table ' . ($striped ? 'table-zebra ' : '') . ($compact ? 'table-compact ' : '') . ($bordered ? 'table-bordered ' : '') . 'w-full ' . $class)]) }}>
        @if(!empty($columns))
            <thead>
                <tr>
                    @foreach($columns as $col)
                        <th>{{ is_array($col) ? ($col['label'] ?? ucfirst($col['key'] ?? '')) : ucfirst($col) }}</th>
                    @endforeach
                    @if(isset($actions)) <th class="text-right">{{ __('Actions') }}</th> @endif
                </tr>
            </thead>
        @endif

        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $col)
                        @php $key = is_array($col) ? ($col['key'] ?? '') : $col; @endphp
                        <td>{{ is_object($row) ? ($row->{$key} ?? '—') : ($row[$key] ?? '—') }}</td>
                    @endforeach
                    @if(isset($actions))
                        <td class="text-right">{{ $actions }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) + (isset($actions) ? 1 : 0) }}" class="text-center py-8 text-base-content/50">
                        {{ __('No data') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
