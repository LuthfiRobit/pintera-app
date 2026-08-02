<div x-show="activeTab === 'ekstrakurikuler'" x-data="{ openAdd: false, openEdit: false, editUrl: '', editData: {} }" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Daftar Ekstrakurikuler Sekolah</h2>
                <p class="text-sm text-gray-500">Kelola berbagai kegiatan pengembangan diri, olahraga, seni, dan kepemimpinan untuk siswa.</p>
            </div>
            @can('lembaga.edit')
                <button type="button" @click="openAdd = !openAdd; openEdit = false;" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="add" class="h-4 w-4" />
                    <span x-text="openAdd ? 'Tutup Formulir' : 'Tambah Ekstrakurikuler'">Tambah Ekstrakurikuler</span>
                </button>
            @endcan
        </div>

        {{-- Form Inline Tambah Ekstrakurikuler --}}
        <div x-show="openAdd" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-brand-200 bg-brand-50/20 p-6 shadow-card" style="display: none;">
            <h3 class="mb-4 font-bold text-gray-900">Formulir Tambah Ekstrakurikuler Baru</h3>
            <form method="POST" action="{{ route('admin.lembaga.ekstrakurikuler.store', $lembaga) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Jenis Ekstrakurikuler *" />
                        <select name="jenis_ekskul" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" disabled selected>Pilih Jenis Kegiatan</option>
                            @foreach (['Olahraga', 'Seni & Budaya', 'Keagamaan', 'Kepemimpinan & Kepramukaan', 'Sains & Karya Ilmiah', 'Keterampilan & Vokasi', 'Lainnya'] as $jenis)
                                <option value="{{ strtolower($jenis) }}">{{ $jenis }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-1 lg:col-span-2">
                        <x-input-label value="Nama Ekstrakurikuler *" />
                        <input type="text" name="nama_ekskul" required placeholder="Contoh: Futsal Putra, Pramuka Gugus Depan, Robotik Club" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Nomor SK Pembentukan" />
                        <input type="text" name="no_sk" placeholder="Contoh: SK-EKS/2026/001" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Tanggal SK" />
                        <input type="date" name="tanggal_sk" value="{{ date('Y-m-d') }}" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Alokasi Jam / Minggu" />
                        <input type="number" name="jam_per_minggu" min="1" max="50" value="2" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openAdd = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Simpan Ekstrakurikuler</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Form Inline Edit Ekstrakurikuler --}}
        <div x-show="openEdit" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-amber-200 bg-amber-50/30 p-6 shadow-card" style="display: none;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-amber-900">Edit Data Ekstrakurikuler</h3>
                <button type="button" @click="openEdit = false" class="text-gray-400 hover:text-gray-600"><x-icon name="close" class="h-5 w-5" /></button>
            </div>
            <form method="POST" x-bind:action="editUrl" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Jenis Ekstrakurikuler *" />
                        <select name="jenis_ekskul" x-model="editData.jenis_ekskul" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" disabled>Pilih Jenis Kegiatan</option>
                            @foreach (['Olahraga', 'Seni & Budaya', 'Keagamaan', 'Kepemimpinan & Kepramukaan', 'Sains & Karya Ilmiah', 'Keterampilan & Vokasi', 'Lainnya'] as $jenis)
                                <option value="{{ strtolower($jenis) }}">{{ $jenis }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-1 lg:col-span-2">
                        <x-input-label value="Nama Ekstrakurikuler *" />
                        <input type="text" name="nama_ekskul" x-model="editData.nama_ekskul" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Nomor SK Pembentukan" />
                        <input type="text" name="no_sk" x-model="editData.no_sk" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Tanggal SK" />
                        <input type="date" name="tanggal_sk" x-model="editData.tanggal_sk" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Alokasi Jam / Minggu" />
                        <input type="number" name="jam_per_minggu" x-model="editData.jam_per_minggu" min="1" max="50" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openEdit = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Perbarui Ekstrakurikuler</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Tabel Ekstrakurikuler --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            @if ($lembaga->ekstrakurikuler->isEmpty())
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                        <x-icon name="sports_basketball" class="h-8 w-8" />
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900">Belum Ada Ekstrakurikuler</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Silakan klik tombol "Tambah Ekstrakurikuler" di atas untuk menambahkan program kegiatan pengembangan diri siswa.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-4">Nama Kegiatan &amp; Jenis</th>
                                <th class="px-6 py-4">Nomor &amp; Tanggal SK</th>
                                <th class="px-6 py-4">Alokasi Waktu</th>
                                @can('lembaga.edit')
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-normal">
                            @foreach ($lembaga->ekstrakurikuler as $ekskul)
                                <tr class="transition hover:bg-gray-50/50">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-gray-900 text-base">{{ $ekskul->nama_ekskul }}</span>
                                        <span class="block text-xs font-semibold uppercase tracking-wide text-brand-600 mt-0.5">{{ $ekskul->jenis_ekskul }}</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs">
                                        <span class="font-bold text-gray-800 block">{{ $ekskul->no_sk ?: 'Tanpa No. SK' }}</span>
                                        @if($ekskul->tanggal_sk)
                                            <span class="text-gray-500 font-sans">Tgl: {{ \Carbon\Carbon::parse($ekskul->tanggal_sk)->translatedFormat('d M Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">
                                            <x-icon name="schedule" class="h-3.5 w-3.5 text-emerald-500" />
                                            {{ $ekskul->jam_per_minggu ?: 2 }} Jam / Minggu
                                        </span>
                                    </td>
                                    @can('lembaga.edit')
                                        <td class="px-6 py-4 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <button type="button" @click="editUrl = @js(route('admin.lembaga.ekstrakurikuler.update', [$lembaga, $ekskul])); editData = @js([
                                                    'id' => $ekskul->id,
                                                    'jenis_ekskul' => $ekskul->jenis_ekskul,
                                                    'nama_ekskul' => $ekskul->nama_ekskul,
                                                    'no_sk' => $ekskul->no_sk,
                                                    'tanggal_sk' => $ekskul->tanggal_sk ? \Carbon\Carbon::parse($ekskul->tanggal_sk)->format('Y-m-d') : '',
                                                    'jam_per_minggu' => $ekskul->jam_per_minggu,
                                                ]); openEdit = true; openAdd = false; window.scrollTo({ top: 0, behavior: 'smooth' });" class="rounded-lg p-1.5 text-gray-500 transition hover:bg-amber-50 hover:text-amber-600 active:scale-95" title="Edit Ekstrakurikuler">
                                                    <x-icon name="edit" class="h-4 w-4" />
                                                </button>

                                                <form method="POST" action="{{ route('admin.lembaga.ekstrakurikuler.destroy', [$lembaga, $ekskul]) }}" class="inline" x-data @submit.prevent="confirmDialog('Hapus Ekstrakurikuler?', @js('Apakah Anda yakin ingin menghapus ekstrakurikuler ' . $ekskul->nama_ekskul . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-error-50 hover:text-error-600 active:scale-95" title="Hapus Ekstrakurikuler">
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
