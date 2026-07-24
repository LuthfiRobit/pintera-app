{{--
    resources/views/components/portal-file-dropzone.blade.php

    Click-or-drag file upload zone — replaces the raw browser "Choose File" button
    with a styled dropzone, an empty-state prompt, and a selected-file preview with a
    Hapus action. The real <input type="file"> stays in the DOM (still submits with
    the form, still gets @required/validation) but is stretched invisibly over the
    whole zone so any click on it opens the native file picker.
--}}
@props(['name', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'PDF/JPG/PNG, maks 2MB'])

<div
    x-data="{ namaFile: null, ukuranFile: null, dragging: false }"
    class="relative overflow-hidden rounded-xl border-2 border-dashed p-5 text-center transition"
    :class="dragging ? 'border-portal-500 bg-portal-50' : (namaFile ? 'border-portal-500/40 bg-portal-50' : 'border-gray-200 hover:border-gray-300')"
    @dragover.prevent="dragging = true"
    @dragleave.prevent="dragging = false"
    @drop.prevent="
        dragging = false;
        $refs.input.files = $event.dataTransfer.files;
        $refs.input.dispatchEvent(new Event('change'));
    "
>
    <input
        type="file"
        name="{{ $name }}"
        x-ref="input"
        accept="{{ $accept }}"
        @change="
            const berkas = $event.target.files[0] ?? null;
            namaFile = berkas?.name ?? null;
            ukuranFile = berkas ? (berkas.size / 1024).toFixed(0) + ' KB' : null;
        "
        class="absolute inset-0 z-0 h-full w-full cursor-pointer opacity-0"
        @required($required)
    />

    <div class="pointer-events-none relative z-10" x-show="!namaFile">
        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-portal-50 text-portal-500">
            <x-icon name="upload" class="h-5 w-5" />
        </span>
        <p class="text-[13px] font-semibold text-gray-900">Klik atau seret file ke sini</p>
        <p class="mt-1 text-[11px] text-gray-400">{{ $hint }}</p>
    </div>

    <div class="pointer-events-none relative z-10 flex items-center justify-center gap-2.5" x-show="namaFile" x-cloak>
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
            <x-icon name="description" class="h-4 w-4" />
        </span>
        <span class="min-w-0 text-left">
            <span class="block truncate text-[12.5px] font-semibold text-gray-900" x-text="namaFile"></span>
            <span class="block text-[11px] text-gray-400" x-text="ukuranFile"></span>
        </span>
        <button
            type="button"
            class="pointer-events-auto shrink-0 text-[11.5px] font-semibold text-error-700"
            @click="$refs.input.value = ''; namaFile = null; ukuranFile = null"
        >Hapus</button>
    </div>
</div>
