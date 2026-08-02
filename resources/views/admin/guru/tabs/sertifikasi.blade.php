<div x-show="activeTab === 'sertifikasi'" x-data="{ openAdd: false }" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Data Sertifikasi & NRG</h2>
                <p class="text-sm text-gray-500">Daftar sertifikat pendidik, profesi, atau lisensi keahlian dan Nomor Registrasi Guru.</p>
            </div>
            @can('guru.edit')
                <button type="button" @click="openAdd = !openAdd" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="add" class="h-4 w-4" />
                    <span x-text="openAdd ? 'Tutup Formulir' : 'Tambah Sertifikasi'">Tambah Sertifikasi</span>
                </button>
            @endcan
        </div>

        {{-- Form Inline Tambah Sertifikasi --}}
        <div x-show="openAdd" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-brand-200 bg-brand-50/20 p-6 shadow-card" style="display: none;">
            <h3 class="mb-4 font-bold text-gray-900">Formulir Tambah Data Sertifikasi</h3>
            <form method="POST" action="{{ route('admin.guru.sertifikasi.store', $guru) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Jenis Sertifikasi *" />
                        <input type="text" name="jenis_sertifikasi" required placeholder="Contoh: Sertifikasi Pendidik / PPG" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Nomor Sertifikat *" />
                        <input type="text" name="nomor_sertifikat" required placeholder="Nomor registrasi pada sertifikat" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Tahun Sertifikasi *" />
                        <input type="number" name="tahun_sertifikasi" required min="1970" max="{{ date('Y') }}" value="{{ date('Y') }}" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Bidang Studi" />
                        <input type="text" name="bidang_studi_sertifikasi" placeholder="Contoh: Matematika" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="NRG (Nomor Registrasi Guru)" />
                        <input type="text" name="nrg" placeholder="Nomor Registrasi Guru (opsional)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Kode/Penyelenggara" />
                        <input type="text" name="kode_lembaga_sertifikasi" placeholder="LPTK atau Institusi Penyelenggara" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openAdd = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Simpan Sertifikasi</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Tabel Sertifikasi --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            @if ($guru->sertifikasi->isEmpty())
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                        <x-icon name="verified_user" class="h-8 w-8" />
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900">Belum Ada Data Sertifikasi</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Silakan klik tombol tambah di atas untuk mencatat kepemilikan sertifikat pendidik atau profesi.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-4">Jenis Sertifikasi & Tahun</th>
                                <th class="px-6 py-4">No. Sertifikat / NRG</th>
                                <th class="px-6 py-4">Bidang Studi</th>
                                <th class="px-6 py-4">Penyelenggara</th>
                                @can('guru.edit')
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-normal">
                            @foreach ($guru->sertifikasi as $item)
                                <tr class="transition hover:bg-gray-50/50">
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        {{ $item->jenis_sertifikasi }}
                                        <span class="block text-xs font-semibold text-brand-600">Tahun: {{ $item->tahun_sertifikasi }}</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono">
                                        <span class="font-bold text-gray-900">{{ $item->nomor_sertifikat }}</span>
                                        @if ($item->nrg)
                                            <span class="block font-sans text-xs font-semibold text-emerald-600">NRG: {{ $item->nrg }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">{{ $item->bidang_studi_sertifikasi ?: '-' }}</td>
                                    <td class="px-6 py-4 text-gray-500">{{ $item->kode_lembaga_sertifikasi ?: '-' }}</td>
                                    @can('guru.edit')
                                        <td class="px-6 py-4 text-right">
                                            <form method="POST" action="{{ route('admin.guru.sertifikasi.destroy', [$guru, $item]) }}" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data sertifikasi ini?');">
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
