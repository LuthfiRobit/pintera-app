<div x-show="activeTab === 'pendidikan'" x-data="{ openAdd: false }" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Riwayat Pendidikan Formal</h2>
                <p class="text-sm text-gray-500">Catatan jenjang studi akademis, institusi, gelar, dan jurusan guru.</p>
            </div>
            @can('guru.edit')
                <button type="button" @click="openAdd = !openAdd" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="add" class="h-4 w-4" />
                    <span x-text="openAdd ? 'Tutup Formulir' : 'Tambah Pendidikan'">Tambah Pendidikan</span>
                </button>
            @endcan
        </div>

        {{-- Form Inline Tambah Pendidikan --}}
        <div x-show="openAdd" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-brand-200 bg-brand-50/20 p-6 shadow-card" style="display: none;">
            <h3 class="mb-4 font-bold text-gray-900">Formulir Tambah Riwayat Pendidikan</h3>
            <form method="POST" action="{{ route('admin.guru.riwayat-pendidikan.store', $guru) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Jenjang *" />
                        <select name="jenjang_pendidikan" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">-- Pilih Jenjang --</option>
                            @foreach(['SMA/Sederajat', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'] as $j)
                                <option value="{{ $j }}">{{ $j }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Nama Sekolah / Kampus *" />
                        <input type="text" name="sekolah_formal" required placeholder="Contoh: Universitas Negeri Malang" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Gelar Akademik" />
                        <input type="text" name="gelar_akademik" placeholder="Contoh: S.Pd." class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Fakultas" />
                        <input type="text" name="fakultas" placeholder="Contoh: Fakultas Ilmu Pendidikan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Jurusan / Bidang Studi" />
                        <input type="text" name="bidang_studi" placeholder="Contoh: Pendidikan Matematika" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Tahun Lulus *" />
                        <input type="number" name="tahun_lulus" required min="1950" max="{{ date('Y') + 5 }}" value="{{ date('Y') }}" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openAdd = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Simpan Data Pendidikan</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Tabel Riwayat Pendidikan --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            @if ($guru->riwayatPendidikan->isEmpty())
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                        <x-icon name="school" class="h-8 w-8" />
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900">Belum Ada Riwayat Pendidikan</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Silakan klik tombol tambah di atas untuk mencatat data perguruan tinggi atau jenjang pendidikan guru.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-4">Jenjang & Gelar</th>
                                <th class="px-6 py-4">Sekolah / Perguruan Tinggi</th>
                                <th class="px-6 py-4">Jurusan & Fakultas</th>
                                <th class="px-6 py-4">Tahun Lulus</th>
                                @can('guru.edit')
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-normal">
                            @foreach ($guru->riwayatPendidikan as $item)
                                <tr class="transition hover:bg-gray-50/50">
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        {{ $item->jenjang_pendidikan }}
                                        @if ($item->gelar_akademik)
                                            <span class="ml-1 rounded bg-brand-50 px-2 py-0.5 font-mono text-xs font-bold text-brand-700">{{ $item->gelar_akademik }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $item->sekolah_formal }}</td>
                                    <td class="px-6 py-4 text-gray-600">
                                        {{ $item->bidang_studi ?: '-' }}
                                        @if ($item->fakultas)
                                            <span class="block text-xs text-gray-400">Fakultas: {{ $item->fakultas }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 font-mono font-semibold text-gray-700">{{ $item->tahun_lulus }}</td>
                                    @can('guru.edit')
                                        <td class="px-6 py-4 text-right">
                                            <form method="POST" action="{{ route('admin.guru.riwayat-pendidikan.destroy', [$guru, $item]) }}" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus riwayat pendidikan ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-error-50 hover:text-error-600 active:scale-95" title="Hapus Data">
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
