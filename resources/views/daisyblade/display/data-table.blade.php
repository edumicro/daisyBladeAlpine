{{--
    Tipo 3: server-driven data table via Alpine + the HTTP client of http.js (the injected client, else axios, else fetch).

    @prop loadUrl   — GET endpoint (required). Must return:
                       { data: [...rows], meta: { current_page, last_page, total, per_page } }
    @prop columns   — [ ['key'=>'name', 'label'=>'Name', 'sortable'=>true] ]
    @prop filters   — filters config forwarded to <x-dbl::form.filters> (optional)
    @prop params    — extra static query params merged on every request
    @prop searchable — show global search input
    @prop perPage   — default page size
    @prop actions   — slot for per-row action buttons (receives $row via Alpine :data-row)
    @prop toolbar   — header links that carry the table's CURRENT state (page, search, sort,
                      filters) as query params:
                        [ ['label'=>'Excel', 'url'=>'/students/export', 'icon'=>'heroicon-o-arrow-down-tray'] ]
                      `download` defaults to true; set it to false for a link that should open
                      in the page (a printable view, say) rather than be saved.
                      Format agnostic on purpose: this component knows nothing about
                      spreadsheets, CSV or PDF. It hands the state to a URL and that URL decides
                      what to build. Add a second entry for a second format.

    Usage:
      <x-dbl::display.data-table
          load-url="/api/users"
          :columns="[['key'=>'name','label'=>'Name','sortable'=>true], ...]"
          :filters="[['key'=>'status','type'=>'select','options'=>[...]]]"
      >
          <x-slot:rowActions>
              <a :href="`/users/${row.id}/edit`" class="btn btn-xs btn-ghost">Edit</a>
          </x-slot:rowActions>
      </x-dbl::display.data-table>
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
    'tableClass'     => '',
    'toolbar'        => [],
])

@php
\Edumicro\DaisyBlade\Support\RequiredUrl::check('display.data-table', 'load-url', $loadUrl);

$config = [
    'loadUrl'  => $loadUrl,
    'params'   => array_merge(['per_page' => $perPage], $params),
    'messages' => ['loadFailed' => __('Could not load the data')],
];
@endphp

<div
    x-data="dblDataTable(@js($config))"
    {{ $attributes }}
>
    {{-- Header. form.filters announces two events, and the table listens to nothing else. They are not on the root, which
         takes the attributes of the caller (a `@dbl-filters-applied.window` of theirs would be a second one).
         With the `filters` prop the panel is INSIDE this header, so the events reach it by rising: no `.window`, which
         would also answer to the filters of every other table on the page. Without it, the filters are somewhere else
         in the page and the table listens on the window. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4"
        @if(!empty($filters))
            @dbl-filters-applied="applyFilters($event.detail.filters)"
            @dbl-filters-cleared="clearFilters()"
        @else
            @dbl-filters-applied.window="applyFilters($event.detail.filters)"
            @dbl-filters-cleared.window="clearFilters()"
        @endif
    >
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
                        class="input input-sm pl-9 w-48 focus:w-64 transition-all"
                    />
                </div>
            @endif

            @if(!empty($filters))
                <x-dbl::form.filters :filters="$filters" />
            @endif

            {{-- Toolbar links: same state the user is looking at, handed to whatever URL. --}}
            @foreach($toolbar as $item)
                <a
                    :href="stateUrl(@js($item['url']))"
                    class="btn btn-ghost btn-sm gap-2"
                    @if($item['download'] ?? true) download @endif
                    title="{{ $item['label'] ?? '' }}"
                >
                    @isset($item['icon'])
                        {{-- Full blade-heroicons component name, e.g. heroicon-o-arrow-down-tray --}}
                        <x-dynamic-component :component="$item['icon']" class="w-4 h-4" />
                    @endisset
                    @if($item['label'] ?? false)
                        <span class="hidden sm:inline">{{ $item['label'] }}</span>
                    @endif
                </a>
            @endforeach

            {{-- Reload button --}}
            <button type="button" @click="load()" :class="loading ? 'loading' : ''" class="btn btn-ghost btn-sm btn-circle" title="{{ __('Refresh') }}">
                <span x-show="!loading"><x-heroicon-o-arrow-path class="w-4 h-4" /></span>
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-lg border border-base-300 {{ $tableClass }}">
        <table class="table table-zebra w-full">
            <thead>
                <tr>
                    @foreach($columns as $col)
                        <th>
                            @if($col['sortable'] ?? false)
                                <button type="button"
                                    @click="sortBy(@js($col['key']))"
                                    class="flex items-center gap-1 font-semibold hover:text-primary transition-colors"
                                >
                                    {{ $col['label'] ?? ucfirst($col['key']) }}
                                    <span class="flex flex-col text-base-content/30">
                                        <x-heroicon-m-chevron-up class="w-3 h-3 -mb-1"
                                            ::class="sort === @js($col['key']) && direction === 'asc' ? 'text-primary' : ''" />
                                        <x-heroicon-m-chevron-down class="w-3 h-3"
                                            ::class="sort === @js($col['key']) && direction === 'desc' ? 'text-primary' : ''" />
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
                {{-- Loading skeleton — always renders regardless of column count --}}
                <template x-if="loading">
                    @php $skelCols = max(count($columns), 3); @endphp
                    @for($r = 0; $r < 5; $r++)
                        <tr>
                            @for($c = 0; $c < $skelCols; $c++)
                                <td><div class="skeleton h-4 rounded w-full"></div></td>
                            @endfor
                            @if(isset($rowActions)) <td></td> @endif
                        </tr>
                    @endfor
                </template>

                {{-- The request failed: a message and a way to try again, never a table that waits for ever --}}
                <template x-if="!loading && error">
                    <tr>
                        <td colspan="{{ count($columns) + (isset($rowActions) ? 1 : 0) }}" class="text-center py-12">
                            <div role="alert" class="flex flex-col items-center gap-3 text-error">
                                <x-heroicon-o-exclamation-triangle class="w-10 h-10 opacity-60" />
                                <span x-text="error"></span>
                                <button type="button" @click="load()" class="btn btn-sm btn-outline">{{ __('Retry') }}</button>
                            </div>
                        </td>
                    </tr>
                </template>

                {{-- Rows --}}
                <template x-if="!loading">
                    <template x-for="(row, idx) in rows" :key="row.id ?? idx">
                        <tr class="hover">
                            @foreach($columns as $col)
                                <td x-text="row[@js($col['key'])] ?? '—'"></td>
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
                <template x-if="!loading && !error && rows.length === 0">
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
