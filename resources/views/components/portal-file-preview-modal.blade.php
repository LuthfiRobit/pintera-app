{{--
    resources/views/components/portal-file-preview-modal.blade.php

    Single shared preview modal driven by the global $store.filePreview Alpine
    store — any "Lihat" button anywhere on the page calls
    $store.filePreview.buka(url, mimeType, namaFile) to open it. PDFs get a
    page-flip book (PDF.js renders each page to canvas, page-flip turns them);
    images just show directly, since a "book" metaphor doesn't fit a single photo.
--}}
<div
    x-data="{}"
    x-show="$store.filePreview.terbuka"
    x-cloak
    x-transition.opacity
    @keydown.escape.window="$store.filePreview.tutup()"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/70 p-4"
    @click.self="$store.filePreview.tutup()"
>
    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3.5">
            <p class="truncate text-[13.5px] font-semibold text-gray-900" x-text="$store.filePreview.nama"></p>
            <button type="button" class="shrink-0 text-gray-400 hover:text-gray-700" @click="$store.filePreview.tutup()" aria-label="Tutup pratinjau">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        <div class="flex flex-1 items-center justify-center overflow-auto bg-gray-50 p-6">
            <p x-show="$store.filePreview.memuat" x-cloak class="text-[13px] text-gray-400">Memuat pratinjau...</p>
            <p x-show="$store.filePreview.error" x-cloak class="text-[13px] text-error-700" x-text="$store.filePreview.error"></p>

            <img
                x-show="$store.filePreview.tipe === 'image' && !$store.filePreview.memuat"
                x-cloak
                :src="$store.filePreview.url"
                :alt="$store.filePreview.nama"
                class="max-h-[70vh] max-w-full rounded-lg object-contain"
            />

            {{--
                page-flip's own destroy() removes whatever container element it was
                given, so the actual #file-preview-flipbook target is (re)created by
                JS inside this stable wrapper on every render — this wrapper itself
                is never passed to page-flip and never gets removed.

                Kept in the layout via visibility (not display:none) while loading —
                page-flip needs to measure this element's real width/height to size
                the book responsively, and a display:none ancestor always measures 0x0.
            --}}
            <div
                id="file-preview-flipbook-wrapper"
                x-show="$store.filePreview.tipe === 'pdf' && !$store.filePreview.error"
                x-cloak
                :class="$store.filePreview.memuat ? 'invisible' : 'visible'"
                class="mx-auto max-h-[70vh] w-full max-w-[480px]"
                style="aspect-ratio: 3 / 4;"
            ></div>
        </div>

        <div class="flex justify-end border-t border-gray-200 px-5 py-3">
            <a :href="$store.filePreview.url" target="_blank" class="text-[12.5px] font-semibold text-portal-500">Unduh file asli</a>
        </div>
    </div>
</div>
