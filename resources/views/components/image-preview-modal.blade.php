<div
    x-data="{
        get store() { return $store.imagePreview; }
    }"
    x-show="$store.imagePreview.terbuka"
    x-cloak
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window="$store.imagePreview.tutup()"
    class="fixed inset-0 z-[70] flex flex-col bg-gray-950/80 backdrop-blur-sm select-none"
    style="display: none;"
>
    {{-- Header Toolbar --}}
    <div class="flex items-center justify-between border-b border-gray-800/80 bg-gray-900/90 px-4 py-3 text-white">
        <div class="flex items-center gap-3 min-w-0">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-800 text-brand-400">
                <x-icon name="image" class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <h3 class="truncate text-xs sm:text-sm font-bold text-gray-100" x-text="$store.imagePreview.judul || 'Pratinjau Dokumen / Foto'"></h3>
                <p class="text-[10px] sm:text-[11px] text-gray-400 font-mono" x-show="!$store.imagePreview.isPdf">
                    Zoom: <span x-text="Math.round($store.imagePreview.zoom * 100) + '%'"></span> &bull; Rotasi: <span x-text="$store.imagePreview.rotation + '°'"></span>
                </p>
            </div>
        </div>

        {{-- Toolbar Actions --}}
        <div class="flex items-center gap-1 sm:gap-2">
            {{-- Image Controls (Only shown for non-PDF) --}}
            <template x-if="!$store.imagePreview.isPdf">
                <div class="flex items-center gap-1 bg-gray-800/90 rounded-lg p-1 border border-gray-700">
                    <button
                        type="button"
                        @click="$store.imagePreview.zoomOut()"
                        class="p-1.5 rounded hover:bg-gray-700 text-gray-300 hover:text-white transition"
                        title="Perkecil (Zoom Out)"
                    >
                        <x-icon name="remove" class="h-4 w-4" />
                    </button>

                    <button
                        type="button"
                        @click="$store.imagePreview.resetZoom()"
                        class="px-2 py-1 text-[11px] font-mono font-bold rounded hover:bg-gray-700 text-gray-300 hover:text-white transition"
                        title="Reset Ukuran (100%)"
                    >
                        <span x-text="Math.round($store.imagePreview.zoom * 100) + '%'"></span>
                    </button>

                    <button
                        type="button"
                        @click="$store.imagePreview.zoomIn()"
                        class="p-1.5 rounded hover:bg-gray-700 text-gray-300 hover:text-white transition"
                        title="Perbesar (Zoom In)"
                    >
                        <x-icon name="add" class="h-4 w-4" />
                    </button>

                    <span class="h-4 w-px bg-gray-700 mx-0.5"></span>

                    <button
                        type="button"
                        @click="$store.imagePreview.rotate()"
                        class="p-1.5 rounded hover:bg-gray-700 text-gray-300 hover:text-white transition"
                        title="Putar 90° Searah Jarum Jam"
                    >
                        <x-icon name="rotate_right" class="h-4 w-4" />
                    </button>
                </div>
            </template>

            {{-- Download Original File --}}
            <a
                :href="$store.imagePreview.url"
                download
                target="_blank"
                class="inline-flex items-center gap-1.5 rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-gray-200 hover:bg-gray-700 hover:text-white border border-gray-700 transition"
                title="Unduh Berkas Asli"
            >
                <x-icon name="download" class="h-4 w-4" />
                <span class="hidden sm:inline">Unduh</span>
            </a>

            {{-- Close Button --}}
            <button
                type="button"
                @click="$store.imagePreview.tutup()"
                class="rounded-lg bg-gray-800 p-1.5 text-gray-400 hover:bg-rose-600 hover:text-white border border-gray-700 transition ml-1"
                aria-label="Tutup Pratinjau"
            >
                <x-icon name="close" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- Main Viewport Canvas --}}
    <div
        class="relative flex-1 overflow-hidden flex items-center justify-center p-4"
        @click.self="$store.imagePreview.tutup()"
        @wheel="$store.imagePreview.onWheel($event)"
    >
        {{-- Image Display --}}
        <template x-if="!$store.imagePreview.isPdf">
            <div
                class="relative cursor-grab active:cursor-grabbing transition-transform duration-75 ease-out"
                :style="`transform: translate(${$store.imagePreview.posX}px, ${$store.imagePreview.posY}px) scale(${$store.imagePreview.zoom}) rotate(${$store.imagePreview.rotation}deg);`"
                @mousedown="$store.imagePreview.startDrag($event)"
            >
                <img
                    :src="$store.imagePreview.url"
                    :alt="$store.imagePreview.judul"
                    class="max-h-[82vh] max-w-[90vw] rounded-lg shadow-2xl object-contain pointer-events-none"
                    draggable="false"
                >
            </div>
        </template>

        {{-- PDF Display --}}
        <template x-if="$store.imagePreview.isPdf">
            <div class="h-full w-full max-w-5xl rounded-xl overflow-hidden bg-white shadow-2xl">
                <iframe :src="$store.imagePreview.url" class="h-full w-full border-0"></iframe>
            </div>
        </template>
    </div>

    {{-- Footer Hint --}}
    <div class="border-t border-gray-800/80 bg-gray-900/80 px-4 py-2 text-center text-[11px] text-gray-400">
        <span class="hidden sm:inline">Gunakan <b>Scroll Mouse</b> atau tombol <b>+ / -</b> untuk zoom, <b>Klik & Geser</b> untuk memindahkan gambar, dan tombol <b>Esc</b> untuk menutup.</span>
        <span class="sm:hidden">Pencet tombol <b>+ / -</b> untuk zoom dan <b>Putar</b> untuk membalik nota.</span>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('imagePreview', {
            terbuka: false,
            url: '',
            judul: '',
            isPdf: false,
            zoom: 1,
            rotation: 0,
            posX: 0,
            posY: 0,
            isDragging: false,
            startX: 0,
            startY: 0,

            buka(url, judul = '', isPdf = null) {
                if (!url) return;
                this.url = url;
                this.judul = judul;
                if (isPdf !== null) {
                    this.isPdf = Boolean(isPdf);
                } else {
                    const u = url.toLowerCase();
                    const j = (judul || '').toLowerCase();
                    this.isPdf = u.endsWith('.pdf') || u.includes('.pdf?') || u.includes('inline=1') || u.includes('preview=1') || j.endsWith('.pdf');
                }
                this.zoom = 1;
                this.rotation = 0;
                this.posX = 0;
                this.posY = 0;
                this.isDragging = false;
                this.terbuka = true;
            },

            tutup() {
                this.terbuka = false;
                this.url = '';
                this.judul = '';
                this.zoom = 1;
                this.rotation = 0;
                this.posX = 0;
                this.posY = 0;
            },

            zoomIn() {
                if (this.zoom < 3.5) {
                    this.zoom = Math.round((this.zoom + 0.25) * 100) / 100;
                }
            },

            zoomOut() {
                if (this.zoom > 0.5) {
                    this.zoom = Math.round((this.zoom - 0.25) * 100) / 100;
                }
            },

            resetZoom() {
                this.zoom = 1;
                this.rotation = 0;
                this.posX = 0;
                this.posY = 0;
            },

            rotate() {
                this.rotation = (this.rotation + 90) % 360;
            },

            onWheel(e) {
                if (this.isPdf) return;
                e.preventDefault();
                if (e.deltaY < 0) {
                    this.zoomIn();
                } else {
                    this.zoomOut();
                }
            },

            startDrag(e) {
                if (this.isPdf) return;
                this.isDragging = true;
                this.startX = e.clientX - this.posX;
                this.startY = e.clientY - this.posY;

                const onMouseMove = (moveEvent) => {
                    if (!this.isDragging) return;
                    this.posX = moveEvent.clientX - this.startX;
                    this.posY = moveEvent.clientY - this.startY;
                };

                const onMouseUp = () => {
                    this.isDragging = false;
                    window.removeEventListener('mousemove', onMouseMove);
                    window.removeEventListener('mouseup', onMouseUp);
                };

                window.addEventListener('mousemove', onMouseMove);
                window.addEventListener('mouseup', onMouseUp);
            }
        });
    });
</script>
