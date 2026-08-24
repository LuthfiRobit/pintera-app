{{-- resources/views/portals/lembaga/keuangan/pembayaran/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Verifikasi Pembayaran</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-4" x-data="pembayaranTable({ dataUrl: @js(route('admin.pembayaran.data')) })">
        @if ($lembagaBelumDipilih ?? false)
            <div class="rounded-xl bg-signal-amber/10 p-4 text-sm text-signal-amber">
                Pilih lembaga aktif melalui pengalih lembaga untuk melihat antrian pembayaran.
            </div>
        @else
            <x-panel>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-5 py-3 font-display font-semibold">Calon Murid</th>
                            <th class="px-5 py-3 font-display font-semibold">Jenis</th>
                            <th class="px-5 py-3 font-display font-semibold">Sumber</th>
                            <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        <template x-for="row in rows" :key="row.id">
                            <tr>
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-ink" x-text="row.nama_calon_murid"></p>
                                    <p class="font-mono text-xs text-slate" x-text="row.kode_pendaftaran"></p>
                                </td>
                                <td class="px-5 py-3.5 text-ink" x-text="row.jenis"></td>
                                <td class="px-5 py-3.5 text-slate" x-text="row.sumber === 'calon_siswa' ? 'Calon Siswa' : 'Admin'"></td>
                                <td class="px-5 py-3.5">
                                    <a :href="'/admin/spmb-pendaftaran/' + row.pendaftaran_id" class="text-sm font-semibold text-ink hover:underline">Tinjau &rarr;</a>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="4" class="px-5 py-10 text-center text-slate">Tidak ada pembayaran yang menunggu verifikasi.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex items-center justify-between border-t border-ink/10 p-4 text-sm text-slate">
                    <p>Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span> &middot; <span x-text="meta.total"></span> menunggu</p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Sebelumnya</button>
                        <button type="button" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Berikutnya</button>
                    </div>
                </div>
            </x-panel>
        @endif
    </div>
</x-app-layout>
