<div
    x-data="{ open: false, siswaId: null, siswaNama: '', loading: false, html: '' }"
    x-on:open-riwayat-modal.window="
        open = true; siswaId = $event.detail.siswaId; siswaNama = $event.detail.siswaNama; loading = true; html = '';
        fetch(`{{ url('admin/virtual-account') }}/${siswaId}/riwayat`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text()).then(t => { html = t; loading = false; })
            .catch(() => { loading = false; html = '<p class=\'text-sm text-error-600\'>Gagal memuat riwayat.</p>'; });
    "
    x-show="open"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
    x-cloak
    style="display: none;"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        class="fixed inset-0 transform transition-all"
        @click="open = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    {{-- Modal Box --}}
    <div
        x-show="open"
        class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-xl sm:w-full z-10 p-6 relative text-left max-h-[90vh] flex flex-col"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200 shrink-0">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="history" class="h-5 w-5 text-brand-600" />
                    <span>Riwayat Pembayaran VA</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Siswa: <span class="font-semibold text-gray-800" x-text="siswaNama"></span></p>
            </div>
            <button @click="open = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        {{-- Content --}}
        <div class="mt-4 flex-1 overflow-y-auto pr-1">
            <div x-show="loading" class="py-8 text-center text-sm text-gray-400">
                <p>Memuat riwayat transaksi...</p>
            </div>
            <div x-show="!loading" x-html="html"></div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end pt-4 mt-6 border-t border-gray-100 shrink-0">
            <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                Tutup
            </button>
        </div>
    </div>
</div>
