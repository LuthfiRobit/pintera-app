<div
    x-data="{ open: false, siswaId: null, siswaNama: '', loading: false, html: '' }"
    x-on:open-riwayat-modal.window="
        open = true; siswaId = $event.detail.siswaId; siswaNama = $event.detail.siswaNama; loading = true; html = '';
        fetch(`{{ url('admin/virtual-account') }}/${siswaId}/riwayat`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text()).then(t => { html = t; loading = false; })
            .catch(() => { loading = false; html = '<p class=\'text-sm text-error-600\'>Gagal memuat riwayat.</p>'; });
    "
    x-show="open"
    style="display: none;"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
>
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-elevated" @click.outside="open = false">
        <div class="flex items-center justify-between">
            <h3 class="font-display text-sm font-bold text-gray-900">Riwayat Pembayaran VA — <span x-text="siswaNama"></span></h3>
            <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <div class="mt-4 max-h-96 overflow-y-auto" x-show="loading">
            <p class="text-sm text-gray-400">Memuat...</p>
        </div>
        <div class="mt-4 max-h-96 overflow-y-auto" x-show="!loading" x-html="html"></div>
    </div>
</div>
