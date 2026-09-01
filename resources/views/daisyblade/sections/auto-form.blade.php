{{--
    Tipo 3: schema-driven form with Alpine + Axios submit.

    @prop fields      — [ 'key' => ['type'=>'text', 'label'=>'...', 'cols'=>'col-span-6', ...] ]
    @prop values      — initial values object (JSON serializable)
    @prop action      — POST endpoint URL (required for Axios mode)
    @prop method      — 'POST'|'PUT'|'PATCH'|'DELETE' (default POST)
    @prop mode        — 'alpine' (Axios, default) | 'form' (standard HTML POST)
    @prop submitLabel — button label
    @prop resetLabel  — optional reset button label
    @prop successMsg  — inline success message shown after Axios submit
    @prop redirect    — redirect URL on success (overrides successMsg if server returns data.redirect)

    Server must return:
      success: { redirect?: string, message?: string }
      error:   422 with { errors: { field: ['msg'] } }
--}}
@props([
    'fields'       => [],
    'values'       => [],
    'action'       => '',
    'method'       => 'POST',
    'mode'         => 'alpine',
    'submitLabel'  => '',
    'resetLabel'   => '',
    'successMsg'   => '',
    'label'        => '',
    'class'        => '',
    'containerClass' => '',
])

@if($mode === 'alpine')
    <div
        x-data="dbAutoForm({
            action: '{{ $action }}',
            method: '{{ strtoupper($method) }}',
            {{-- (object) y no @js($values) a secas: en PHP un array asociativo VACIO se serializa
                 como [] —un array JS—, no como {}. x-model escribe values.email sin protestar,
                 pero JSON.stringify descarta las propiedades con nombre de un array y axios manda
                 [] al servidor: el formulario se envia vacio y el fallo se ve como un 422 de
                 "campo obligatorio". Afecta a todo formulario sin valores iniciales, es decir a
                 todos los de alta. --}}
            values: @js((object) $values),
        })"
        {{ $attributes->merge(['class' => $containerClass]) }}
    >
        @if($label)
            <h3 class="text-lg font-semibold mb-4">{{ $label }}</h3>
        @endif

        {{-- Success banner --}}
        @if($successMsg)
            <div x-show="success" x-cloak class="alert alert-success mb-4">
                <x-heroicon-o-check-circle class="w-5 h-5" />
                <span>{{ $successMsg }}</span>
            </div>
        @endif

        {{-- Global error --}}
        <div x-show="globalError" x-cloak class="alert alert-error mb-4">
            <x-heroicon-o-x-circle class="w-5 h-5" />
            <span x-text="globalError"></span>
        </div>

        <form @submit.prevent="submit" class="{{ $class }}">
            <div class="grid grid-cols-12 gap-4">
                @include('daisyblade::form.fields', [
                    'fields'      => $fields,
                    'mode'        => 'alpine',
                    'alpineData'  => 'values',
                    'alphaErrors' => 'errors',
                ])
            </div>

            {{ $slot }}

            <div class="flex items-center gap-3 mt-6">
                <button
                    type="submit"
                    :disabled="loading"
                    class="btn btn-primary"
                >
                    <span x-show="loading" class="loading loading-spinner loading-sm" x-cloak></span>
                    {{ $submitLabel ?: __('Save') }}
                </button>

                @if($resetLabel)
                    <button type="reset" class="btn btn-ghost">{{ $resetLabel }}</button>
                @endif
            </div>
        </form>
    </div>
@else
    {{-- Standard HTML form mode --}}
    <div {{ $attributes->merge(['class' => $containerClass]) }}>
        @if($label)
            <h3 class="text-lg font-semibold mb-4">{{ $label }}</h3>
        @endif

        <form
            action="{{ $action }}"
            method="{{ in_array(strtoupper($method), ['GET','POST']) ? strtoupper($method) : 'POST' }}"
            class="{{ $class }}"
        >
            @csrf
            @if(!in_array(strtoupper($method), ['GET','POST']))
                @method($method)
            @endif

            <div class="grid grid-cols-12 gap-4">
                @include('daisyblade::form.fields', [
                    'fields'  => $fields,
                    'mode'    => 'form',
                    'values'  => $values,
                ])
            </div>

            {{ $slot }}

            <div class="flex items-center gap-3 mt-6">
                <button type="submit" class="btn btn-primary">
                    {{ $submitLabel ?: __('Save') }}
                </button>
                @if($resetLabel)
                    <button type="reset" class="btn btn-ghost">{{ $resetLabel }}</button>
                @endif
            </div>
        </form>
    </div>
@endif
