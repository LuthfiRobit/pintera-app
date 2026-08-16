<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 text-sm font-medium text-emerald-800 border border-emerald-200">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-xl bg-rose-50 p-4 text-sm font-medium text-rose-800 border border-rose-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-xl font-bold text-gray-900">Master Ruangan & Fasilitas</h1>
                <p class="text-xs text-gray-500 mt-1">Kelola direktori ruang kelas, laboratorium, kantor, dan fasilitas sekolah.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.sarpras.ruangan.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                    <x-icon name="add" class="h-4 w-4" />
                    Tambah Ruangan
                </a>
            </div>
        </div>

        {{-- Table Container with Filters --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="{{ route('admin.sarpras.ruangan.index') }}" class="flex flex-wrap items-center gap-3 w-full">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari kode atau nama ruangan..." class="w-full sm:w-64 rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    
                    <select name="gedung_id" class="rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">Semua Gedung</option>
                        @foreach($gedungOptions as $g)
                            <option value="{{ $g->id }}" {{ request('gedung_id') == $g->id ? 'selected' : '' }}>{{ $g->nama_gedung }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 transition">Filter</button>
                    @if(request()->hasAny(['search', 'gedung_id', 'jenis_ruangan']))
                        <a href="{{ route('admin.sarpras.ruangan.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead class="bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3.5">Kode</th>
                            <th class="px-6 py-3.5">Nama Ruangan</th>
                            <th class="px-6 py-3.5">Gedung / Lantai</th>
                            <th class="px-6 py-3.5">Jenis Ruangan</th>
                            <th class="px-6 py-3.5 text-center">Kapasitas</th>
                            <th class="px-6 py-3.5 text-center">Total Aset</th>
                            <th class="px-6 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($ruanganList as $ruangan)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-6 py-4 font-mono font-bold text-gray-900">
                                    <a href="{{ route('admin.sarpras.ruangan.show', $ruangan) }}" class="text-indigo-600 hover:underline">
                                        {{ $ruangan->kode_ruangan }}
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900">{{ $ruangan->nama_ruangan }}</div>
                                    @if($ruangan->is_shared)
                                        <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700 mt-1">Shared Facility</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-900">{{ $ruangan->gedung->nama_gedung ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">Lantai {{ $ruangan->lantai }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold text-gray-800">
                                        {{ $ruangan->jenis_ruangan->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">{{ $ruangan->kapasitas_siswa ? $ruangan->kapasitas_siswa . ' Siswa' : '-' }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                        {{ $ruangan->aset_count }} Item
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <a href="{{ route('admin.sarpras.kir.show', $ruangan) }}" class="inline-flex items-center text-emerald-600 hover:text-emerald-900 font-semibold text-xs" title="Lihat Kartu Inventaris Ruangan">KIR</a>
                                    <a href="{{ route('admin.sarpras.ruangan.edit', $ruangan) }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-900 font-semibold text-xs">Edit</a>
                                    <form action="{{ route('admin.sarpras.ruangan.destroy', $ruangan) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus ruangan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-900 font-semibold text-xs">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                    Belum ada data ruangan yang terdaftar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($ruanganList->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $ruanganList->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
