<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Verifikasi &amp; Keputusan Pendaftaran</h2>
    </x-slot>

    <div
        class="mx-auto max-w-6xl space-y-6"
        x-data="pendaftaranTable({
            dataUrl: @js(route('admin.spmb-pendaftaran.data')),
            showUrlTemplate: @js(route('admin.spmb-pendaftaran.show', ['pendaftaran' => '__ID__'])),
        })"
    >
        <x-panel>
            <div class="flex flex-wrap items-center gap-3 border-b border-ink/10 p-4">
                <input
                    type="search"
                    x-model="search"
                    @input="onSearchInput()"
                    placeholder="Cari nama atau kode pendaftaran..."
                    class="w-full max-w-xs rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                >
                <select
                    x-model="status"
                    @change="onStatusChange()"
                    class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                >
                    <option value="">Semua Status</option>
                    <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                    <option value="diterima">Diterima</option>
                    <option value="ditolak">Ditolak</option>
                </select>
                <button
                    type="button"
                    @click="fetchData()"
                    class="ml-auto inline-flex items-center gap-2 rounded-xl border border-ink/15 px-3 py-2 text-sm font-medium text-ink hover:bg-paper"
                >
                    <span x-show="loading" class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-ink/30 border-t-ink"></span>
                    Refresh
                </button>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                        <th class="px-5 py-3 font-display font-semibold">Calon Murid</th>
                        <th class="px-5 py-3 font-display font-semibold">Jalur / Gelombang</th>
                        <th class="px-5 py-3 font-display font-semibold">Dokumen</th>
                        <th class="px-5 py-3 font-display font-semibold">Status</th>
                        <th class="px-5 py-3 font-display font-semibold">Tanggal Submit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    <template x-for="row in rows" :key="row.id">
                        <tr class="cursor-pointer transition hover:bg-paper/50" @click="window.location.href = showUrl(row)">
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-ink" x-text="row.nama_calon_murid"></p>
                                <p class="font-mono text-xs text-slate" x-text="row.kode_pendaftaran"></p>
                            </td>
                            <td class="px-5 py-3.5 text-ink">
                                <span x-text="row.jalur"></span> &middot; <span class="text-slate" x-text="row.gelombang"></span>
                            </td>
                            <td class="px-5 py-3.5 text-slate">
                                <span x-text="row.dokumen_terverifikasi"></span>/<span x-text="row.dokumen_total"></span> terverifikasi
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                                    :class="{
                                        'bg-signal-amber/10 text-signal-amber': row.status === 'menunggu_verifikasi',
                                        'bg-signal-green/10 text-signal-green': row.status === 'diterima',
                                        'bg-signal-red/10 text-signal-red': row.status === 'ditolak',
                                    }"
                                    x-text="row.status === 'menunggu_verifikasi' ? 'Menunggu Verifikasi' : (row.status === 'diterima' ? 'Diterima' : 'Ditolak')"
                                ></span>
                            </td>
                            <td class="px-5 py-3.5 text-slate" x-text="row.submitted_at"></td>
                        </tr>
                    </template>
                    <tr x-show="!loading && rows.length === 0">
                        <td colspan="5" class="px-5 py-10 text-center text-slate">Tidak ada pendaftaran yang cocok.</td>
                    </tr>
                </tbody>
            </table>

            <div class="flex items-center justify-between border-t border-ink/10 p-4 text-sm text-slate">
                <p>Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span> &middot; <span x-text="meta.total"></span> pendaftaran</p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Sebelumnya</button>
                    <button type="button" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Berikutnya</button>
                </div>
            </div>
        </x-panel>
    </div>
</x-app-layout>
