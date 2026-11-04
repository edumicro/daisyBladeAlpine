{{--
    Tipo 3: spreadsheet file upload with a confirmation step and progress.

    The browser does not read the file: an .xlsx is a zip of XML, and reading it takes a library the package does not
    ship. So there is no preview of the rows. The confirmation step shows the name and size of the chosen file, and the
    server (which does read it) answers with how many rows it imported.

    @prop uploadUrl    — POST endpoint for the file
    @prop templateUrl  — download link for the import template
    @prop chunkSize    — rows per chunk (passed to server as hint)
    @prop accept       — accepted MIME/extensions
--}}
@props([
    'uploadUrl'   => '',
    'templateUrl' => '',
    'chunkSize'   => 1000,
    'accept'      => '.xlsx,.xls,.csv',
    'label'       => '',
])

@php
\Edumicro\DaisyBlade\Support\RequiredUrl::check('import.spreadsheet', 'upload-url', $uploadUrl);

$config = [
    'uploadUrl'   => $uploadUrl,
    'templateUrl' => $templateUrl,
    'chunkSize'   => (int) $chunkSize,
    'accept'      => $accept,   // the drop zone filters by it too
    'messages'    => ['uploadFailed' => __('Upload failed'), 'wrongType' => __('This file type is not accepted')],
];
@endphp

<div
    x-data="dblSpreadsheetImport(@js($config))"
    {{ $attributes }}
>
    @if($label)
        <h3 class="text-lg font-semibold mb-4">{{ $label }}</h3>
    @endif

    {{-- Step: upload --}}
    <div x-show="step === 'upload'" class="space-y-4">
        <div
            class="border-2 border-dashed border-base-300 rounded-xl p-8 text-center hover:border-primary transition-colors cursor-pointer"
            {{-- The zone is a <div>, so a keyboard needs a way in: it takes focus, and Enter or Space open the file dialog. --}}
            tabindex="0" role="button"
            @click="$refs.fileInput.click()"
            @keydown.enter.prevent="$refs.fileInput.click()"
            @keydown.space.prevent="$refs.fileInput.click()"
            @dragover.prevent
            @drop.prevent="handleDrop($event)"
        >
            <x-heroicon-o-arrow-up-tray class="w-10 h-10 mx-auto mb-3 text-base-content/30" />
            <p class="font-medium">{{ __('Click or drag your file here') }}</p>
            <p class="text-sm text-base-content/50 mt-1">{{ $accept }}</p>

            <input
                type="file"
                x-ref="fileInput"
                accept="{{ $accept }}"
                @change="handleFile($event)"
                class="hidden"
            />
        </div>

        <p x-show="refusal" x-cloak style="display: none" x-text="refusal" class="text-sm text-error text-center" role="alert"></p>

        @if($templateUrl)
            <div class="flex justify-center">
                <a href="{{ $templateUrl }}" class="btn btn-ghost btn-sm gap-2">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    {{ __('Download template') }}
                </a>
            </div>
        @endif
    </div>

    {{-- Step: confirm. The name and size of the chosen file; the rows are counted by the server, not here. --}}
    <div x-show="step === 'confirm'" x-cloak class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <p class="font-medium truncate">
                <span x-text="file?.name"></span>
                <span class="text-sm font-normal text-base-content/60" x-text="`(${fileSizeText})`"></span>
            </p>
            <div class="flex gap-2">
                <button type="button" @click="step = 'upload'" class="btn btn-ghost btn-sm">{{ __('Cancel') }}</button>
                <button type="button" @click="upload()" :disabled="loading" class="btn btn-primary btn-sm">
                    <span x-show="loading" class="loading loading-spinner loading-xs" x-cloak></span>
                    {{ __('Confirm import') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Step: importing --}}
    <div x-show="step === 'importing'" x-cloak class="space-y-3 py-8 text-center">
        <div class="loading loading-spinner loading-lg"></div>
        <p class="font-medium">{{ __('Importing...') }}</p>
        <progress class="progress progress-primary w-full max-w-sm mx-auto" :value="progress" max="100"></progress>
        <p class="text-sm text-base-content/60" x-text="`${progress}%`"></p>
    </div>

    {{-- Step: done --}}
    <div x-show="step === 'done'" x-cloak class="text-center py-8 space-y-3">
        <x-heroicon-o-check-circle class="w-12 h-12 text-success mx-auto" />
        <p class="font-medium text-success">{{ __('Import complete') }}</p>
        <p class="text-sm text-base-content/60" x-text="`${result?.imported ?? 0} {{ __('rows imported') }}`"></p>
        <button type="button" @click="step = 'upload'; file = null" class="btn btn-ghost btn-sm">
            {{ __('Import another file') }}
        </button>
    </div>

    {{-- Step: error --}}
    <div x-show="step === 'error'" x-cloak class="alert alert-error">
        <x-heroicon-o-x-circle class="w-5 h-5" />
        <div>
            <p class="font-medium">{{ __('Import failed') }}</p>
            <p class="text-sm" x-text="error"></p>
        </div>
        <button type="button" @click="step = 'upload'" class="btn btn-ghost btn-sm">{{ __('Try again') }}</button>
    </div>
</div>
