<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Header & Breadcrumbs --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="font-display text-xl font-bold text-gray-900">{{ $ruangan->nama_ruangan }}</h1>
                    <span class="rounded-full bg-gray-100 px-3 py-1 font-mono text-xs font-bold text-gray-700">{{ $ruangan->kode_ruangan }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">Detail spesifikasi ruangan, daftar inventaris, dan jadwal pemakaian.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.sarpras.kir.export', $ruangan) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                    <x-icon name="print" class="h-4 w-4" />
                    Cetak KIR (PDF)
                </a>
                <a href="{{ route('admin.sarpras.ruangan.edit', $ruangan) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition">
                    <x-icon name="edit" class="h-4 w-4" />
                    Edit
                </a>
            </div>
        </div>

        {{-- Info Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase text-gray-400">Gedung & Lantai</p>
                <p class="font-display text-base font-bold text-gray-900 mt-1">{{ $ruangan->gedung->nama_gedung ?? '-' }}</p>
                <p class="text-xs text-gray-500">Lantai {{ $ruangan->lantai }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase text-gray-400">Jenis Ruangan</p>
                <p class="font-display text-base font-bold text-gray-900 mt-1">{{ $ruangan->jenis_ruangan->label() }}</p>
                <p class="text-xs text-gray-500">{{ $ruangan->is_shared ? 'Fasilitas Bersama' : 'Unit Khusus' }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase text-gray-400">Kapasitas / Luas</p>
                <p class="font-display text-base font-bold text-gray-900 mt-1">{{ $ruangan->kapasitas_siswa ?? '-' }} Siswa</p>
                <p class="text-xs text-gray-500">{{ $ruangan->luas_m2 ? $ruangan->luas_m2 . ' m²' : 'Luas Belum Diisi' }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase text-gray-400">Penanggung Jawab</p>
                <p class="font-display text-base font-bold text-gray-900 mt-1">{{ $ruangan->penanggungJawab->nama ?? 'Belum Ditentukan' }}</p>
                <p class="text-xs text-gray-500">{{ $ruangan->penanggungJawab->nip ?? '-' }}</p>
            </div>
        </div>

        {{-- Tab Aset & Jadwal --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden" x-data="{ tab: 'aset' }">
            <div class="border-b border-gray-200 px-4">
                <nav class="flex space-x-6 text-xs font-semibold">
                    <button @click="tab = 'aset'" :class="tab === 'aset' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="py-4 border-b-2 transition">
                        Daftar Aset / Inventaris ({{ $ruangan->aset->count() }})
                    </button>
                    <button @click="tab = 'jadwal'" :class="tab === 'jadwal' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="py-4 border-b-2 transition">
                        Jadwal Pemakaian Ruangan ({{ $ruangan->jadwalPelajaran->count() }})
                    </button>
                </nav>
            </div>

            <div x-show="tab === 'aset'" class="p-4">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead class="bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3">Kode Inventaris</th>
                            <th class="px-4 py-3">Nama Barang</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3 text-center">Jumlah</th>
                            <th class="px-4 py-3 text-center">Kondisi</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($ruangan->aset as $aset)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-4 py-3 font-mono font-bold text-gray-900">{{ $aset->kode_inventaris }}</td>
                                <td class="px-4 py-3">{{ $aset->nama_barang }}</td>
                                <td class="px-4 py-3">{{ $aset->kategori->nama_kategori ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">{{ $aset->qty }} {{ $aset->satuan }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $aset->kondisi->badgeColor() }}">
                                        {{ $aset->kondisi->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.sarpras.aset.show', $aset) }}" class="text-indigo-600 hover:underline font-semibold">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada aset terdaftar di ruangan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="tab === 'jadwal'" class="p-4" style="display: none;">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead class="bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3">Kelas</th>
                            <th class="px-4 py-3">Mata Pelajaran</th>
                            <th class="px-4 py-3">Guru Pengampu</th>
                            <th class="px-4 py-3 text-center">Jam Pelajaran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($ruangan->jadwalPelajaran as $j)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $j->kelas->nama ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $j->mataPelajaran->nama ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $j->guru->nama ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">{{ $j->jamPelajaran->label ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-400">Belum ada jadwal mengajar yang dialokasikan ke ruangan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
