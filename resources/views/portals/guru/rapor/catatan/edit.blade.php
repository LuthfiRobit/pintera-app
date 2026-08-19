<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-5" x-data="catatanWaliKelasForm({
        ekstrakurikuler: @js($catatan->ekstrakurikuler ?? []),
        prestasi: @js($catatan->prestasi ?? []),
        pklInfo: @js($catatan->pkl_info ?? []),
    })">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Catatan Wali Kelas — {{ $siswa->nama_lengkap }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">Semester: {{ $semester->nama }}</p>
            </div>
            <a href="{{ route('guru.rapor.catatan.index', ['kelas_id' => $siswa->kelas_id, 'semester_id' => $semester->id]) }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">
                &larr; Kembali ke Daftar
            </a>
        </div>

        <form method="POST" action="{{ route('guru.rapor.catatan.update', $siswa) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="semester_id" value="{{ $semester->id }}">

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Sikap</label>
                    <textarea name="catatan_sikap" rows="3" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('catatan_sikap', $catatan->catatan_sikap) }}</textarea>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-gray-700">Catatan Perkembangan</label>
                        <button type="button" @click="generateNarasi()" class="text-xs font-semibold text-brand-600 hover:underline">
                            Generate Otomatis
                        </button>
                    </div>
                    <textarea name="catatan_perkembangan" x-ref="catatanPerkembangan" rows="4" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('catatan_perkembangan', $catatan->catatan_perkembangan) }}</textarea>
                </div>

                @if ($tampilkanAntropometri)
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Tinggi Badan (cm)</label>
                            <input type="number" step="0.1" name="tinggi_badan_cm" value="{{ old('tinggi_badan_cm', $catatan->tinggi_badan_cm) }}" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Berat Badan (kg)</label>
                            <input type="number" step="0.1" name="berat_badan_kg" value="{{ old('berat_badan_kg', $catatan->berat_badan_kg) }}" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Lingkar Kepala (cm)</label>
                            <input type="number" step="0.1" name="lingkar_kepala_cm" value="{{ old('lingkar_kepala_cm', $catatan->lingkar_kepala_cm) }}" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold text-gray-700">Ekstrakurikuler</label>
                    <button type="button" @click="ekstrakurikuler.push({nama: '', peran: ''})" class="text-xs font-semibold text-brand-600 hover:underline">+ Tambah Baris</button>
                </div>
                <template x-for="(row, index) in ekstrakurikuler" :key="index">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-5 items-center">
                        <input type="text" :name="`ekstrakurikuler[${index}][nama]`" x-model="row.nama" placeholder="Nama kegiatan" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <input type="text" :name="`ekstrakurikuler[${index}][peran]`" x-model="row.peran" placeholder="Peran (mis. Anggota)" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <button type="button" @click="ekstrakurikuler.splice(index, 1)" class="text-xs font-medium text-error-600 hover:underline">Hapus</button>
                    </div>
                </template>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold text-gray-700">Prestasi</label>
                    <button type="button" @click="prestasi.push({nama: '', tingkat: '', tahun: ''})" class="text-xs font-semibold text-brand-600 hover:underline">+ Tambah Baris</button>
                </div>
                <template x-for="(row, index) in prestasi" :key="index">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-7 items-center">
                        <input type="text" :name="`prestasi[${index}][nama]`" x-model="row.nama" placeholder="Nama prestasi" class="sm:col-span-3 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <input type="text" :name="`prestasi[${index}][tingkat]`" x-model="row.tingkat" placeholder="Tingkat" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <input type="text" :name="`prestasi[${index}][tahun]`" x-model="row.tahun" placeholder="Tahun" class="rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <button type="button" @click="prestasi.splice(index, 1)" class="text-xs font-medium text-error-600 hover:underline">Hapus</button>
                    </div>
                </template>
            </div>

            @if ($tampilkanPklInfo)
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-gray-700">Info PKL</label>
                        <button type="button" @click="pklInfo.push({perusahaan: '', posisi: '', durasi: ''})" class="text-xs font-semibold text-brand-600 hover:underline">+ Tambah Baris</button>
                    </div>
                    <template x-for="(row, index) in pklInfo" :key="index">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-7 items-center">
                            <input type="text" :name="`pkl_info[${index}][perusahaan]`" x-model="row.perusahaan" placeholder="Perusahaan" class="sm:col-span-3 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                            <input type="text" :name="`pkl_info[${index}][posisi]`" x-model="row.posisi" placeholder="Posisi" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                            <input type="text" :name="`pkl_info[${index}][durasi]`" x-model="row.durasi" placeholder="Durasi" class="rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                            <button type="button" @click="pklInfo.splice(index, 1)" class="text-xs font-medium text-error-600 hover:underline">Hapus</button>
                        </div>
                    </template>
                </div>
            @endif

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Keterangan Kenaikan Kelas</label>
                <textarea name="keterangan_kenaikan" rows="2" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('keterangan_kenaikan', $catatan->keterangan_kenaikan) }}</textarea>
            </div>

            <div class="flex items-center justify-between gap-3">
                <div>
                    @if ($siswaSebelumnya)
                        <a href="{{ route('guru.rapor.catatan.edit', ['siswa' => $siswaSebelumnya->id, 'semester_id' => $semester->id]) }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">&larr; Siswa Sebelumnya</a>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button type="submit">Simpan & Kembali ke Daftar</x-primary-button>
                    @if ($siswaBerikutnya)
                        <button type="submit" name="next_siswa_id" value="{{ $siswaBerikutnya->id }}" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                            Simpan & Siswa Berikutnya &rarr;
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <script>
        function catatanWaliKelasForm(initial) {
            return {
                ekstrakurikuler: initial.ekstrakurikuler.length ? initial.ekstrakurikuler : [],
                prestasi: initial.prestasi.length ? initial.prestasi : [],
                pklInfo: initial.pklInfo.length ? initial.pklInfo : [],
                async generateNarasi() {
                    const existing = this.$refs.catatanPerkembangan.value.trim();
                    if (existing && !(await confirmDialog('Timpa Catatan?', 'Draft otomatis akan menimpa isi catatan perkembangan yang sudah ada. Lanjutkan?'))) {
                        return;
                    }
                    const url = @js(route('guru.rapor.catatan.generate-narasi', $siswa)) + '?semester_id=' + @js($semester->id);
                    const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), Accept: 'application/json' } });
                    const data = await response.json();
                    this.$refs.catatanPerkembangan.value = data.narasi;
                },
            };
        }
    </script>
</x-app-layout>
