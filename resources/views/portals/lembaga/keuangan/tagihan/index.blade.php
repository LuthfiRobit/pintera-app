{{-- resources/views/admin/tagihan/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tagihan</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-4" x-data="tagihanTable({ dataUrl: @js(route('admin.tagihan.data')) })">
        @if ($lembagaBelumDipilih ?? false)
            <div class="rounded-xl bg-signal-amber/10 p-4 text-sm text-signal-amber">
                Pilih lembaga aktif melalui pengalih lembaga untuk melihat tagihan.
            </div>
        @else
            <x-panel>
                <div class="flex flex-wrap items-center gap-3 border-b border-ink/10 p-4">
                    <input
                        type="search"
                        x-model="search"
                        @input="onSearchInput()"
                        placeholder="Cari nama atau kode pendaftaran..."
                        class="w-full max-w-xs rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                    >
                    <select x-model="status" @change="onStatusChange()" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">Semua Status</option>
                        <option value="belum_bayar">Belum Bayar</option>
                        <option value="dicicil">Dicicil</option>
                        <option value="lunas">Lunas</option>
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
                            <th class="px-5 py-3 font-display font-semibold">Kategori</th>
                            <th class="px-5 py-3 font-display font-semibold">Total</th>
                            <th class="px-5 py-3 font-display font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        <template x-for="row in rows" :key="row.id">
                            <tr>
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-ink" x-text="row.nama_calon_murid"></p>
                                    <p class="font-mono text-xs text-slate" x-text="row.kode_pendaftaran"></p>
                                </td>
                                <td class="px-5 py-3.5 text-ink" x-text="row.kategori === 'pendaftaran' ? 'Pendaftaran' : 'Daftar Ulang'"></td>
                                <td class="px-5 py-3.5 text-ink" x-text="'Rp ' + Number(row.total_tagihan).toLocaleString('id-ID')"></td>
                                <td class="px-5 py-3.5">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                                        :class="{
                                            'bg-signal-amber/10 text-signal-amber': row.status === 'belum_bayar',
                                            'bg-signal-green/10 text-signal-green': row.status === 'lunas',
                                            'bg-brass/10 text-brass': row.status === 'dicicil',
                                        }"
                                        x-text="row.status === 'belum_bayar' ? 'Belum Bayar' : (row.status === 'lunas' ? 'Lunas' : 'Dicicil')"
                                    ></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="4" class="px-5 py-10 text-center text-slate">Tidak ada tagihan yang cocok.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex items-center justify-between border-t border-ink/10 p-4 text-sm text-slate">
                    <p>Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span> &middot; <span x-text="meta.total"></span> tagihan</p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Sebelumnya</button>
                        <button type="button" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Berikutnya</button>
                    </div>
                </div>
            </x-panel>
        @endif
    </div>
</x-app-layout>
