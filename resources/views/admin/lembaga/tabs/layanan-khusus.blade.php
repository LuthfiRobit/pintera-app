<div x-show="activeTab === 'layanan-khusus'" x-data="{ openAdd: false, openEdit: false, editUrl: '', editData: {} }" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Layanan Khusus &amp; Pendukung Sekolah</h2>
                <p class="text-sm text-gray-500">Pencatatan layanan konsultasi, kesehatan (UKS), asrama, perpustakaan, hingga antar-jemput.</p>
            </div>
            @can('lembaga.edit')
                <button type="button" @click="openAdd = !openAdd; openEdit = false;" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="add" class="h-4 w-4" />
                    <span x-text="openAdd ? 'Tutup Formulir' : 'Tambah Layanan Khusus'">Tambah Layanan Khusus</span>
                </button>
            @endcan
        </div>

        {{-- Form Inline Tambah Layanan Khusus --}}
        <div x-show="openAdd" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-brand-200 bg-brand-50/20 p-6 shadow-card" style="display: none;">
            <h3 class="mb-4 font-bold text-gray-900">Formulir Tambah Layanan Khusus</h3>
            <form method="POST" action="{{ route('admin.lembaga.layanan-khusus.store', $lembaga) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Jenis Layanan Khusus *" />
                        <select name="jenis_layanan" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" disabled selected>Pilih Layanan</option>
                            @foreach (['Bimbingan Konseling (BK)', 'Usaha Kesehatan Sekolah (UKS)', 'Perpustakaan Digital / Sekolah', 'Asrama / Boarding School', 'Transportasi / Antar-Jemput', 'Kantin Sehat Sekolah', 'Koperasi Siswa / Sekolah', 'Laboratorium Spesifik', 'Lainnya'] as $layanan)
                                <option value="{{ $layanan }}">{{ $layanan }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Nomor SK Layanan" />
                        <input type="text" name="no_sk" placeholder="Contoh: SK-LAY/2026/01" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Terhitung Mulai Tanggal (TMT)" />
                        <input type="date" name="tmt" value="{{ date('Y-m-d') }}" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Tanggal Selesai Tugas (TST)" />
                        <input type="date" name="tst" placeholder="Opsional jika berlaku terus" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-2">
                        <x-input-label value="Keterangan / Fasilitas Pendukung" />
                        <input type="text" name="keterangan" placeholder="Contoh: Buka Senin-Jumat 07.00-15.00 WIB, didukung 2 psikolog" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openAdd = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Simpan Layanan Khusus</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Form Inline Edit Layanan Khusus --}}
        <div x-show="openEdit" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-amber-200 bg-amber-50/30 p-6 shadow-card" style="display: none;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-amber-900">Edit Layanan Khusus</h3>
                <button type="button" @click="openEdit = false" class="text-gray-400 hover:text-gray-600"><x-icon name="close" class="h-5 w-5" /></button>
            </div>
            <form method="POST" x-bind:action="editUrl" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Jenis Layanan Khusus *" />
                        <select name="jenis_layanan" x-model="editData.jenis_layanan" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" disabled>Pilih Layanan</option>
                            @foreach (['Bimbingan Konseling (BK)', 'Usaha Kesehatan Sekolah (UKS)', 'Perpustakaan Digital / Sekolah', 'Asrama / Boarding School', 'Transportasi / Antar-Jemput', 'Kantin Sehat Sekolah', 'Koperasi Siswa / Sekolah', 'Laboratorium Spesifik', 'Lainnya'] as $layanan)
                                <option value="{{ $layanan }}">{{ $layanan }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Nomor SK Layanan" />
                        <input type="text" name="no_sk" x-model="editData.no_sk" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Terhitung Mulai Tanggal (TMT)" />
                        <input type="date" name="tmt" x-model="editData.tmt" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Tanggal Selesai Tugas (TST)" />
                        <input type="date" name="tst" x-model="editData.tst" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-2">
                        <x-input-label value="Keterangan / Fasilitas Pendukung" />
                        <input type="text" name="keterangan" x-model="editData.keterangan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openEdit = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Perbarui Layanan Khusus</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Tabel Layanan Khusus --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            @if ($lembaga->layananKhusus->isEmpty())
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                        <x-icon name="support_agent" class="h-8 w-8" />
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900">Belum Ada Layanan Khusus</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Silakan klik tombol "Tambah Layanan Khusus" untuk menambahkan sarana pendukung seperti BK, UKS, atau Asrama.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-4">Jenis Layanan</th>
                                <th class="px-6 py-4">No. SK &amp; Masa Berdaya</th>
                                <th class="px-6 py-4">Keterangan / Fasilitas</th>
                                @can('lembaga.edit')
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-normal">
                            @foreach ($lembaga->layananKhusus as $layanan)
                                <tr class="transition hover:bg-gray-50/50">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-gray-900 text-base">{{ $layanan->jenis_layanan }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <span class="font-mono font-semibold text-gray-800 block">{{ $layanan->no_sk ?: 'Tanpa No. SK' }}</span>
                                        <span class="text-gray-500">TMT: {{ $layanan->tmt ? \Carbon\Carbon::parse($layanan->tmt)->format('d/m/Y') : '-' }} @if($layanan->tst) s.d. {{ \Carbon\Carbon::parse($layanan->tst)->format('d/m/Y') }} @else (Aktif) @endif</span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        {{ $layanan->keterangan ?: 'Tidak ada keterangan khusus.' }}
                                    </td>
                                    @can('lembaga.edit')
                                        <td class="px-6 py-4 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <button type="button" @click="editUrl = @js(route('admin.lembaga.layanan-khusus.update', [$lembaga, $layanan])); editData = @js([
                                                    'id' => $layanan->id,
                                                    'jenis_layanan' => $layanan->jenis_layanan,
                                                    'no_sk' => $layanan->no_sk,
                                                    'tmt' => $layanan->tmt ? \Carbon\Carbon::parse($layanan->tmt)->format('Y-m-d') : '',
                                                    'tst' => $layanan->tst ? \Carbon\Carbon::parse($layanan->tst)->format('Y-m-d') : '',
                                                    'keterangan' => $layanan->keterangan,
                                                ]); openEdit = true; openAdd = false; window.scrollTo({ top: 0, behavior: 'smooth' });" class="rounded-lg p-1.5 text-gray-500 transition hover:bg-amber-50 hover:text-amber-600 active:scale-95" title="Edit Layanan Khusus">
                                                    <x-icon name="edit" class="h-4 w-4" />
                                                </button>

                                                <form method="POST" action="{{ route('admin.lembaga.layanan-khusus.destroy', [$lembaga, $layanan]) }}" class="inline" x-data @submit.prevent="confirmDialog('Hapus Layanan Khusus?', @js('Apakah Anda yakin ingin menghapus layanan khusus ' . $layanan->jenis_layanan . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-error-50 hover:text-error-600 active:scale-95" title="Hapus Layanan Khusus">
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
