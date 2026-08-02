<div x-show="activeTab === 'jabatan'" x-data="{ openAdd: false }" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Jabatan Tambahan & Tugas Khusus</h2>
                <p class="text-sm text-gray-500">Penugasan administratif (Wakil Kepala Sekolah, Wali Kelas, Kepala Laboratorium, dll).</p>
            </div>
            @can('guru.edit')
                <button type="button" @click="openAdd = !openAdd" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="add" class="h-4 w-4" />
                    <span x-text="openAdd ? 'Tutup Formulir' : 'Tambah Jabatan'">Tambah Jabatan</span>
                </button>
            @endcan
        </div>

        {{-- Form Inline Tambah Jabatan --}}
        <div x-show="openAdd" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-brand-200 bg-brand-50/20 p-6 shadow-card" style="display: none;">
            <h3 class="mb-4 font-bold text-gray-900">Formulir Penugasan Jabatan Tambahan</h3>
            <form method="POST" action="{{ route('admin.guru.jabatan-tambahan.store', $guru) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <x-input-label value="Pilih Jabatan Master *" />
                        <select name="jabatan_tambahan_master_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">-- Pilih Jabatan Tambahan --</option>
                            @foreach ($jabatanTambahanMasterList as $master)
                                <option value="{{ $master->id }}">{{ $master->nama }} ({{ ucwords($master->kelompok) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Nomor SK Penugasan" />
                        <input type="text" name="no_sk" placeholder="Contoh: 421/05/SK/2025" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Mulai Periode *" />
                        <input type="date" name="mulai_periode" required value="{{ date('Y-m-d') }}" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Akhir Periode (Opsional)" />
                        <input type="date" name="akhir_periode" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openAdd = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Simpan Penugasan</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Tabel Jabatan Tambahan --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            @if ($guru->jabatanTambahan->isEmpty())
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                        <x-icon name="badge" class="h-8 w-8" />
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900">Belum Ada Jabatan Tambahan</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Guru ini belum memiliki amanah atau tugas penunjang di luar tugas utama saat ini.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-4">Jabatan & Kelompok</th>
                                <th class="px-6 py-4">Nomor SK</th>
                                <th class="px-6 py-4">Periode Penugasan</th>
                                @can('guru.edit')
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-normal">
                            @foreach ($guru->jabatanTambahan as $item)
                                <tr class="transition hover:bg-gray-50/50">
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        {{ $item->nama }}
                                        <span class="ml-2 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ ucwords($item->kelompok) }}</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono font-medium text-gray-700">{{ $item->pivot->no_sk ?: '-' }}</td>
                                    <td class="px-6 py-4 text-gray-600">
                                        {{ \Illuminate\Support\Carbon::parse($item->pivot->mulai_periode)->translatedFormat('d F Y') }}
                                        &ndash;
                                        {{ $item->pivot->akhir_periode ? \Illuminate\Support\Carbon::parse($item->pivot->akhir_periode)->translatedFormat('d F Y') : 'Sekarang (Aktif)' }}
                                    </td>
                                    @can('guru.edit')
                                        <td class="px-6 py-4 text-right">
                                            <form method="POST" action="{{ route('admin.guru.jabatan-tambahan.destroy', [$guru, $item->id]) }}" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus atau menonaktifkan penugasan ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-error-50 hover:text-error-600 active:scale-95" title="Hapus Penugasan">
                                                    <x-icon name="delete" class="h-4 w-4" />
                                                </button>
                                            </form>
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
