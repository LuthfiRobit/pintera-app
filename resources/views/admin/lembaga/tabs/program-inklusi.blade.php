<div x-show="activeTab === 'program-inklusi'" x-data="{ openAdd: false, openEdit: false, editUrl: '', editData: {} }" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Program Inklusi &amp; Pendidikan Khusus</h2>
                <p class="text-sm text-gray-500">Dukungan pembelajaran bagi siswa berkebutuhan khusus (ABK), kelas inklusif, dan pendampingan spesialis.</p>
            </div>
            @can('lembaga.edit')
                <button type="button" @click="openAdd = !openAdd; openEdit = false;" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="add" class="h-4 w-4" />
                    <span x-text="openAdd ? 'Tutup Formulir' : 'Tambah Program Inklusi'">Tambah Program Inklusi</span>
                </button>
            @endcan
        </div>

        {{-- Form Inline Tambah Program Inklusi --}}
        <div x-show="openAdd" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-brand-200 bg-brand-50/20 p-6 shadow-card" style="display: none;">
            <h3 class="mb-4 font-bold text-gray-900">Formulir Tambah Program Inklusi</h3>
            <form method="POST" action="{{ route('admin.lembaga.program-inklusi.store', $lembaga) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Kategori Kebutuhan Khusus *" />
                        <select name="kebutuhan_khusus" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" disabled selected>Pilih Kebutuhan Khusus</option>
                            @foreach (['Tuna Netra (A)', 'Tuna Rungu / Wicara (B)', 'Tuna Grahita (C)', 'Tuna Daksa (D)', 'Tuna Laras (E)', 'Autisme Spectrum (Q)', 'ADHD (Gangguan Pemusatan Perhatian)', 'Slow Learner / Lamban Belajar', 'Kesulitan Belajar Spesifik (Dyslexia dll)', 'Cerdas Istimewa & Bakat Istimewa (CI+BI)', 'Lainnya'] as $kebutuhan)
                                <option value="{{ $kebutuhan }}">{{ $kebutuhan }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Nomor SK Inklusi / Tugas" />
                        <input type="text" name="no_sk" placeholder="Contoh: SK-INK/2026/01" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Tanggal SK" />
                        <input type="date" name="tanggal_sk" value="{{ date('Y-m-d') }}" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Terhitung Mulai Tanggal (TMT)" />
                        <input type="date" name="tmt" value="{{ date('Y-m-d') }}" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Tanggal Selesai Tugas (TST)" />
                        <input type="date" name="tst" placeholder="Opsional" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <x-input-label value="Keterangan / Fasilitas Pendukung Inklusi" />
                        <input type="text" name="keterangan" placeholder="Contoh: Didukung Guru Pembimbing Khusus (GPK) dan ruang resource room spesialis" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openAdd = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Simpan Program Inklusi</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Form Inline Edit Program Inklusi --}}
        <div x-show="openEdit" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-amber-200 bg-amber-50/30 p-6 shadow-card" style="display: none;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-amber-900">Edit Program Inklusi</h3>
                <button type="button" @click="openEdit = false" class="text-gray-400 hover:text-gray-600"><x-icon name="close" class="h-5 w-5" /></button>
            </div>
            <form method="POST" x-bind:action="editUrl" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Kategori Kebutuhan Khusus *" />
                        <select name="kebutuhan_khusus" x-model="editData.kebutuhan_khusus" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" disabled>Pilih Kebutuhan Khusus</option>
                            @foreach (['Tuna Netra (A)', 'Tuna Rungu / Wicara (B)', 'Tuna Grahita (C)', 'Tuna Daksa (D)', 'Tuna Laras (E)', 'Autisme Spectrum (Q)', 'ADHD (Gangguan Pemusatan Perhatian)', 'Slow Learner / Lamban Belajar', 'Kesulitan Belajar Spesifik (Dyslexia dll)', 'Cerdas Istimewa & Bakat Istimewa (CI+BI)', 'Lainnya'] as $kebutuhan)
                                <option value="{{ $kebutuhan }}">{{ $kebutuhan }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Nomor SK Inklusi / Tugas" />
                        <input type="text" name="no_sk" x-model="editData.no_sk" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Tanggal SK" />
                        <input type="date" name="tanggal_sk" x-model="editData.tanggal_sk" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Terhitung Mulai Tanggal (TMT)" />
                        <input type="date" name="tmt" x-model="editData.tmt" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Tanggal Selesai Tugas (TST)" />
                        <input type="date" name="tst" x-model="editData.tst" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <x-input-label value="Keterangan / Fasilitas Pendukung Inklusi" />
                        <input type="text" name="keterangan" x-model="editData.keterangan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openEdit = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Perbarui Program Inklusi</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Tabel Program Inklusi --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            @if ($lembaga->programInklusi->isEmpty())
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                        <x-icon name="diversity_1" class="h-8 w-8" />
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900">Belum Ada Program Inklusi</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Silakan klik tombol "Tambah Program Inklusi" untuk mendeskripsikan layanan siswa berkebutuhan khusus.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-4">Kategori Kebutuhan Khusus</th>
                                <th class="px-6 py-4">Nomor &amp; Tanggal SK</th>
                                <th class="px-6 py-4">Masa Berlaku &amp; Keterangan</th>
                                @can('lembaga.edit')
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-normal">
                            @foreach ($lembaga->programInklusi as $inklusi)
                                <tr class="transition hover:bg-gray-50/50">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-gray-900 text-base">{{ $inklusi->kebutuhan_khusus }}</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs">
                                        <span class="font-bold text-gray-800 block">{{ $inklusi->no_sk ?: 'Tanpa No. SK' }}</span>
                                        @if($inklusi->tanggal_sk)
                                            <span class="text-gray-500 font-sans">Tgl SK: {{ \Carbon\Carbon::parse($inklusi->tanggal_sk)->format('d/m/Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-semibold text-brand-600 block">TMT: {{ $inklusi->tmt ? \Carbon\Carbon::parse($inklusi->tmt)->format('d/m/Y') : '-' }} @if($inklusi->tst) s.d. {{ \Carbon\Carbon::parse($inklusi->tst)->format('d/m/Y') }} @else (Aktif) @endif</span>
                                        <p class="text-xs text-gray-600 mt-0.5">{{ $inklusi->keterangan ?: 'Tidak ada keterangan tambahan.' }}</p>
                                    </td>
                                    @can('lembaga.edit')
                                        <td class="px-6 py-4 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <button type="button" @click="editUrl = @js(route('admin.lembaga.program-inklusi.update', [$lembaga, $inklusi])); editData = @js([
                                                    'id' => $inklusi->id,
                                                    'kebutuhan_khusus' => $inklusi->kebutuhan_khusus,
                                                    'no_sk' => $inklusi->no_sk,
                                                    'tanggal_sk' => $inklusi->tanggal_sk ? \Carbon\Carbon::parse($inklusi->tanggal_sk)->format('Y-m-d') : '',
                                                    'tmt' => $inklusi->tmt ? \Carbon\Carbon::parse($inklusi->tmt)->format('Y-m-d') : '',
                                                    'tst' => $inklusi->tst ? \Carbon\Carbon::parse($inklusi->tst)->format('Y-m-d') : '',
                                                    'keterangan' => $inklusi->keterangan,
                                                ]); openEdit = true; openAdd = false; window.scrollTo({ top: 0, behavior: 'smooth' });" class="rounded-lg p-1.5 text-gray-500 transition hover:bg-amber-50 hover:text-amber-600 active:scale-95" title="Edit Program Inklusi">
                                                    <x-icon name="edit" class="h-4 w-4" />
                                                </button>

                                                <form method="POST" action="{{ route('admin.lembaga.program-inklusi.destroy', [$lembaga, $inklusi]) }}" class="inline" x-data @submit.prevent="confirmDialog('Hapus Program Inklusi?', @js('Apakah Anda yakin ingin menghapus program inklusi ' . $inklusi->kebutuhan_khusus . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-error-50 hover:text-error-600 active:scale-95" title="Hapus Program Inklusi">
                                                        <x-icon name="delete" class="h-4 w-4" />
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
