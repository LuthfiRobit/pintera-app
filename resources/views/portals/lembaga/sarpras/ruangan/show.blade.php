<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="font-display text-lg font-bold text-gray-900">{{ $ruangan->nama_ruangan }}</h1>
                    <x-badge tone="slate">{{ $ruangan->kode_ruangan }}</x-badge>
                    @if ($ruangan->is_shared)
                        <x-badge tone="purple">Shared Facility</x-badge>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Detail spesifikasi ruangan, daftar inventaris, dan jadwal pemakaian.</p>
            </div>
            <div class="flex items-center gap-2">
                <x-link-button variant="secondary" href="{{ route('admin.sarpras.ruangan.index') }}">
                    <x-icon name="arrow_back" class="h-4 w-4" /> Kembali
                </x-link-button>
                <a href="{{ route('admin.sarpras.kir.export', $ruangan) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                    <x-icon name="print" class="h-4 w-4" />
                    Cetak KIR (PDF)
                </a>
                <x-link-button href="{{ route('admin.sarpras.ruangan.edit', $ruangan) }}">
                    <x-icon name="edit" class="h-4 w-4" /> Edit
                </x-link-button>
            </div>
        </div>

        {{-- Info KPI Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="apartment" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Gedung / Lantai</p>
                        <p class="font-display text-sm font-bold text-gray-900 leading-tight">{{ $ruangan->gedung->nama_gedung ?? '-' }}</p>
                        <p class="text-[11px] text-gray-400">Lantai {{ $ruangan->lantai }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="category" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Jenis Ruang</p>
                        <p class="font-display text-sm font-bold text-gray-900 leading-tight">{{ $ruangan->jenis_ruangan->label() }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <x-icon name="groups" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Kapasitas / Luas</p>
                        <p class="font-display text-sm font-bold text-gray-900 leading-tight">{{ $ruangan->kapasitas_siswa ?? '-' }} Siswa</p>
                        <p class="text-[11px] text-gray-400">{{ $ruangan->luas_m2 ? $ruangan->luas_m2 . ' m²' : '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="person" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Penanggung Jawab</p>
                        <p class="font-display text-sm font-bold text-gray-900 leading-tight truncate max-w-[120px]">{{ $ruangan->penanggungJawab->nama ?? 'Belum Ada' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabbed Section: Aset & Jadwal --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden" x-data="{ tab: 'aset' }">
            <div class="border-b border-gray-200 px-5">
                <nav class="flex space-x-6 text-xs font-semibold">
                    <button @click="tab = 'aset'" :class="tab === 'aset' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="py-4 border-b-2 transition">
                        Daftar Aset / Inventaris ({{ $ruangan->aset->count() }})
                    </button>
                    <button @click="tab = 'jadwal'" :class="tab === 'jadwal' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="py-4 border-b-2 transition">
                        Jadwal Pemakaian Ruangan ({{ $ruangan->jadwalPelajaran->count() }})
                    </button>
                </nav>
            </div>

            <div x-show="tab === 'aset'" class="p-5">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
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
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3.5 font-mono font-bold text-gray-900">{{ $aset->kode_inventaris }}</td>
                                <td class="px-4 py-3.5 font-semibold text-gray-900">{{ $aset->nama_barang }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ $aset->kategori->nama_kategori ?? '-' }}</td>
                                <td class="px-4 py-3.5 text-center">{{ $aset->qty }} {{ $aset->satuan }}</td>
                                <td class="px-4 py-3.5 text-center">
                                    <x-badge tone="{{ $aset->kondisi->value === 'baik' ? 'green' : ($aset->kondisi->value === 'rusak_ringan' ? 'amber' : 'rose') }}">
                                        {{ $aset->kondisi->label() }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <a href="{{ route('admin.sarpras.aset.show', $aset) }}" class="text-xs font-semibold text-brand-600 hover:underline">Detail Aset</a>
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

            <div x-show="tab === 'jadwal'" class="p-5" style="display: none;">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
                            <th class="px-4 py-3">Kelas</th>
                            <th class="px-4 py-3">Mata Pelajaran</th>
                            <th class="px-4 py-3">Guru Pengampu</th>
                            <th class="px-4 py-3 text-center">Jam Pelajaran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($ruangan->jadwalPelajaran as $j)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3.5 font-semibold text-gray-900">{{ $j->kelas->nama ?? '-' }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ $j->mataPelajaran->nama ?? '-' }}</td>
                                <td class="px-4 py-3.5 text-gray-600">{{ $j->guru->nama ?? '-' }}</td>
                                <td class="px-4 py-3.5 text-center font-semibold text-gray-900">{{ $j->jamPelajaran->label ?? '-' }}</td>
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
