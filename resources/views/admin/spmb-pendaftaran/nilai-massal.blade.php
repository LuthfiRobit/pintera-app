<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Input Nilai Massal</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <x-panel class="p-6">
            <form method="GET" action="{{ route('admin.spmb-pendaftaran.nilai-massal') }}" class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Pilih Jenis Tes / Gelombang" />
                    <select name="seleksi_ppdb_id" onchange="this.form.submit()" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">Pilih...</option>
                        @foreach ($daftarSeleksi as $seleksi)
                            <option value="{{ $seleksi->id }}" @selected($seleksiTerpilih?->id === $seleksi->id)>
                                {{ $seleksi->jenisTesMaster->nama }} — {{ $seleksi->jalurPpdb->nama }} / {{ $seleksi->gelombangPpdb->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </x-panel>

        @if ($seleksiTerpilih)
            <x-panel>
                <div
                    x-data="nilaiMassal({
                        seleksiPpdbId: {{ $seleksiTerpilih->id }},
                        storeUrl: @js(route('admin.spmb-pendaftaran.nilai-massal.store')),
                        initialNilai: @js($pesertaList->mapWithKeys(fn ($p) => [$p->id => $p->hasilSeleksi->first()?->nilai])),
                    })"
                >
                    <div class="border-b border-ink/10 px-6 py-4">
                        <h3 class="font-display font-semibold text-ink">{{ $seleksiTerpilih->jenisTesMaster->nama }}</h3>
                        <p class="mt-0.5 text-sm text-slate">{{ $pesertaList->count() }} peserta</p>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                                <th class="px-6 py-3 font-display font-semibold">Calon Murid</th>
                                <th class="px-6 py-3 font-display font-semibold">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/10">
                            @foreach ($pesertaList as $peserta)
                                <tr>
                                    <td class="px-6 py-3 text-ink">{{ $peserta->calonMurid->nama_lengkap }}</td>
                                    <td class="px-6 py-3">
                                        <input
                                            type="number" min="0" max="100" step="0.01"
                                            x-model="nilai[{{ $peserta->id }}]"
                                            class="w-28 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="border-t border-ink/10 p-4">
                        <button
                            type="button"
                            :disabled="saving"
                            @click="simpan()"
                            class="inline-flex items-center gap-2 rounded-xl bg-ink px-4 py-2.5 text-sm font-bold text-paper shadow-sm transition hover:bg-ink/90 disabled:opacity-60"
                        >
                            <span x-text="saving ? 'Menyimpan...' : 'Simpan Semua Nilai'"></span>
                        </button>
                    </div>
                </div>
            </x-panel>
        @endif
    </div>
</x-app-layout>
