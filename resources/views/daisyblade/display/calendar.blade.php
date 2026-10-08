@props([
    'loadUrl',
    'locale'       => null,
    'initialDate'  => null,
    'initialView'  => null,   // 'month' | 'list'. Sin valor: mes en escritorio, lista en móvil (< sm).
    'weekStartsOn' => 1,      // 0 = domingo, 1 = lunes
    'height'       => null,   // p. ej. '40rem'; sin valor, crece con el contenido
])

@php
$config = [
    'loadUrl'      => $loadUrl,
    'locale'       => $locale ?? app()->getLocale(),
    'initialDate'  => $initialDate,
    'initialView'  => $initialView,
    'weekStartsOn' => (int) $weekStartsOn,
];
@endphp

<div {{ $attributes->merge(['class' => 'db-calendar']) }} x-data="dbCalendar(@js($config))">
    {{-- Barra: navegación + cambio de vista --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <div class="join">
            <button type="button" class="btn btn-sm join-item" x-on:click="prev()" aria-label="{{ __('daisyblade::calendar.previous') }}">&lsaquo;</button>
            <button type="button" class="btn btn-sm join-item" x-on:click="goToday()">{{ __('daisyblade::calendar.today') }}</button>
            <button type="button" class="btn btn-sm join-item" x-on:click="next()" aria-label="{{ __('daisyblade::calendar.next') }}">&rsaquo;</button>
        </div>

        <h2 class="text-lg font-bold capitalize" aria-live="polite" x-text="title"></h2>

        <div class="join">
            <button type="button" class="btn btn-sm join-item" x-bind:class="view === 'month' && 'btn-active'" x-on:click="setView('month')">{{ __('daisyblade::calendar.month') }}</button>
            <button type="button" class="btn btn-sm join-item" x-bind:class="view === 'list' && 'btn-active'" x-on:click="setView('list')">{{ __('daisyblade::calendar.list') }}</button>
        </div>
    </div>

    <div @if($height) style="height: {{ $height }}; overflow-y: auto" @endif>
        {{-- Cargando --}}
        <div x-show="loading" x-cloak>
            <x-dbl::feedback.skeleton type="table" />
        </div>

        {{-- Error con reintento --}}
        <div x-show="error && !loading" x-cloak>
            <div role="alert" class="alert alert-error">
                <span>{{ __('daisyblade::calendar.error') }}</span>
                <button type="button" class="btn btn-sm" x-on:click="load()">{{ __('daisyblade::calendar.retry') }}</button>
            </div>
        </div>

        {{-- Vista mes --}}
        <div x-show="view === 'month' && !error" x-cloak>
            <div class="grid grid-cols-7 text-center text-xs font-semibold text-base-content/60 mb-1">
                <template x-for="name in weekdays" :key="name">
                    <div class="py-1 capitalize" x-text="name"></div>
                </template>
            </div>

            <div class="grid grid-cols-7 border-t border-l border-base-300">
                <template x-for="cell in grid" :key="cell.date">
                    <div class="border-r border-b border-base-300 min-h-20 p-1 min-w-0"
                         x-bind:class="cell.inMonth ? '' : 'bg-base-200/50 text-base-content/40'">
                        <button type="button"
                                x-bind:data-day="cell.date"
                                x-bind:tabindex="focused === cell.date ? 0 : -1"
                                x-bind:aria-label="dayLabel(cell.date)"
                                class="btn btn-ghost btn-xs btn-circle"
                                x-bind:class="cell.date === today && 'bg-primary text-primary-content'"
                                x-on:click="selectDay(cell.date)"
                                x-on:keydown="onDayKeydown($event)"
                                x-text="dayNumber(cell.date)"></button>

                        <template x-for="ev in dayEvents(cell.date).slice(0, 3)" :key="ev.id">
                            <button type="button"
                                    class="block w-full truncate text-left text-xs rounded bg-base-200 px-1 py-0.5 mt-0.5 border-l-4 border-primary hover:bg-base-300"
                                    x-bind:style="ev.color && `border-left-color: ${ev.color}`"
                                    x-bind:title="ev.title"
                                    x-on:click="open(ev)">
                                <span class="opacity-70" x-text="timeLabel(ev)"></span>
                                <span x-text="ev.title"></span>
                            </button>
                        </template>

                        <button type="button" class="link text-xs mt-0.5"
                                x-show="dayEvents(cell.date).length > 3"
                                x-on:click="selectDay(cell.date)"
                                x-text="@js(__('daisyblade::calendar.more')).replace(':n', dayEvents(cell.date).length - 3)"></button>
                    </div>
                </template>
            </div>

            {{-- Lista del día elegido ("+n más" o Enter sobre un día) --}}
            <div class="mt-4 card bg-base-200 card-border" x-show="selectedDay" x-cloak>
                <div class="card-body p-4">
                    <h3 class="font-bold capitalize" x-text="selectedDay && dayLabel(selectedDay)"></h3>
                    <p class="text-sm text-base-content/60" x-show="selectedDay && !dayEvents(selectedDay).length">{{ __('daisyblade::calendar.empty') }}</p>
                    <ul class="space-y-1">
                        <template x-for="ev in dayEvents(selectedDay)" :key="ev.id">
                            <li>
                                <a class="link" x-bind:href="ev.url || null" x-on:click="emit(ev)">
                                    <span class="opacity-70" x-text="timeLabel(ev)"></span>
                                    <span x-text="ev.title"></span>
                                </a>
                                <span class="text-sm text-base-content/60" x-show="ev.subtitle" x-text="ev.subtitle"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Vista lista: agrupada por día --}}
        <div x-show="view === 'list' && !error && !loading" x-cloak class="space-y-4">
            <template x-for="day in listDays" :key="day">
                <section>
                    <h3 class="font-bold capitalize border-b border-base-300 pb-1 mb-2"
                         x-bind:class="day === today && 'text-primary'" x-text="dayLabel(day)"></h3>
                    <ul class="space-y-2">
                        <template x-for="ev in dayEvents(day)" :key="ev.id">
                            <li class="flex gap-3 border-l-4 border-primary pl-3"
                                x-bind:style="ev.color && `border-left-color: ${ev.color}`">
                                <span class="w-14 shrink-0 text-sm text-base-content/70"
                                      x-text="timeLabel(ev) || @js(__('daisyblade::calendar.all_day'))"></span>
                                <div class="min-w-0">
                                    <a class="link font-medium" x-show="ev.url" x-bind:href="ev.url" x-on:click="emit(ev)" x-text="ev.title"></a>
                                    <span class="font-medium" x-show="!ev.url" x-text="ev.title"></span>
                                    <div class="text-sm text-base-content/60" x-show="ev.subtitle" x-text="ev.subtitle"></div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </section>
            </template>
        </div>

        {{-- Vacío --}}
        <p class="text-center py-8 text-base-content/50" x-show="isEmpty" x-cloak>{{ __('daisyblade::calendar.empty') }}</p>
    </div>
</div>
