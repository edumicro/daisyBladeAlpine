{{--
    Tipo 3: server-driven data table via Alpine + Axios.

    @prop loadUrl   — Axios GET endpoint. Must return:
                       { data: [...rows], meta: { current_page, last_page, total, per_page } }
    @prop columns   — [ ['key'=>'name', 'label'=>'Name', 'sortable'=>true] ]
    @prop filters   — filters config forwarded to <x-db::display.filters> (optional)
    @prop params    — extra static query params merged on every request
    @prop searchable — show global search input
    @prop perPage   — default page size
    @prop actions   — slot for per-row action buttons (receives $row via Alpine :data-row)

    Usage:
      <x-db::display.data-table
          load-url="/api/users"
          :columns="[['key'=>'name','label'=>'Name','sortable'=>true], ...]"
          :filters="[['key'=>'status','type'=>'select','options'=>[...]]]"
      >
          <x-slot:rowActions>
              <a :href="`/users/${row.id}/edit`" class="btn btn-xs btn-ghost">Edit</a>
          </x-slot:rowActions>
      </x-db::display.data-table>
--}}
@props([
    'loadUrl'        => '',
    'columns'        => [],
    'filters'        => [],
    'params'         => [],
    'searchable'     => true,
    'perPage'        => 15,
    'label'          => '',
    'emptyText'      => '',
    'class'          => '',
    'containerClass' => '',
])

<div
    x-data="dbDataTable({
        loadUrl: '{{ $loadUrl }}',
        params:  @js(array_merge(['per_page' => $perPage], $params)),
    })"
    x-init="init()"
    @filters-updated.window="Object.assign(params, $event.detail.filters); page = 1; load()"
    @filters-cleared.window="Object.keys($event.detail?.filters ?? {}).forEach(k => delete params[k]); page = 1; load()"
    {{ $attributes->merge(['class' => $containerClass]) }}
>
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            @if($label) <h3 class="text-lg font-bold">{{ $label }}</h3> @endif
        </div>

        <div class="flex items-center gap-2">
            @if($searchable)
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                    <input
                        type="text"
                        x-model="search"
                        @input.debounce.400="page = 1; load()"
                        placeholder="{{ __('Search...') }}"
                        class="input input-bordered input-sm pl-9 w-48 focus:w-64 transition-all"
                    />
                </div>
            @endif

            @if(!empty($filters))
                <x-db::display.filters :filters="$filters" />
            @endif

            {{-- Reload button --}}
            <button type="button" @click="load()" :class="loading ? 'loading' : ''" class="btn btn-ghost btn-sm btn-circle" title="{{ __('Refresh') }}">
                <span x-show="!loading"><x-heroicon-o-arrow-path class="w-4 h-4" /></span>
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-lg border border-base-300 {{ $class }}">
        <table class="table table-zebra w-full">
            <thead>
                <tr>
                    @foreach($columns as $col)
                        <th>
                            @if($col['sortable'] ?? false)
                                <button type="button"
                                    @click="sortBy('{{ $col['key'] }}')"
                                    class="flex items-center gap-1 font-semibold hover:text-primary transition-colors"
                                >
                                    {{ $col['label'] ?? ucfirst($col['key']) }}
                                    <span class="flex flex-col text-base-content/30">
                                        <x-heroicon-m-chevron-up class="w-3 h-3 -mb-1"
                                            ::class="sort === '{{ $col['key'] }}' && direction === 'asc' ? 'text-primary' : ''" />
                                        <x-heroicon-m-chevron-down class="w-3 h-3"
                                            ::class="sort === '{{ $col['key'] }}' && direction === 'desc' ? 'text-primary' : ''" />
                                    </span>
                                </button>
                            @else
                                {{ $col['label'] ?? ucfirst($col['key']) }}
                            @endif
                        </th>
                    @endforeach
                    @if(isset($rowActions)) <th class="text-right">{{ __('Actions') }}</th> @endif
                </tr>
            </thead>

            <tbody>
                {{-- Loading skeleton --}}
                <template x-if="loading">
                    @for($r = 0; $r < 5; $r++)
                        <tr>
                            @foreach($columns as $col)
                                <td><div class="skeleton h-4 rounded w-full"></div></td>
                            @endforeach
                            @if(isset($rowActions)) <td></td> @endif
                        </tr>
                    @endfor
                </template>

                {{-- Rows --}}
                <template x-if="!loading">
                    <template x-for="(row, idx) in rows" :key="row.id ?? idx">
                        <tr class="hover">
                            @foreach($columns as $col)
                                <td x-text="row['{{ $col['key'] }}'] ?? '—'"></td>
                            @endforeach
                            @if(isset($rowActions))
                                <td class="text-right" x-data="{ row: row }">
                                    {{ $rowActions }}
                                </td>
                            @endif
                        </tr>
                    </template>
                </template>

                {{-- Empty state --}}
                <template x-if="!loading && rows.length === 0">
                    <tr>
                        <td colspan="{{ count($columns) + (isset($rowActions) ? 1 : 0) }}" class="text-center py-12 text-base-content/50">
                            <x-heroicon-o-inbox class="w-10 h-10 mx-auto mb-3 opacity-30" />
                            {{ $emptyText ?: __('No data') }}
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="flex items-center justify-between mt-4 text-sm text-base-content/70">
        <span x-show="meta.total > 0" x-text="`{{ __('Showing') }} ${((meta.current_page-1)*meta.per_page)+1}–${Math.min(meta.current_page*meta.per_page, meta.total)} {{ __('of') }} ${meta.total}`"></span>
        <span x-show="meta.total === 0">{{ __('No results') }}</span>

        <div x-show="meta.last_page > 1" class="join" x-cloak>
            <button type="button" @click="goTo(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="join-item btn btn-sm">‹</button>

            <template x-for="p in meta.last_page" :key="p">
                <button type="button"
                    @click="goTo(p)"
                    :class="p === meta.current_page ? 'btn-active' : ''"
                    class="join-item btn btn-sm"
                    x-text="p"
                    x-show="Math.abs(p - meta.current_page) <= 2 || p === 1 || p === meta.last_page"
                ></button>
            </template>

            <button type="button" @click="goTo(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="join-item btn btn-sm">›</button>
        </div>
    </div>
</div>
