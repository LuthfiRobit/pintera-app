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

        {{-- Header & Breadcrumbs --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-xl font-bold text-gray-900">Master Gedung & Bangunan</h1>
                <p class="text-xs text-gray-500 mt-1">Kelola data gedung, jumlah lantai, dan status fasilitas fisik.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.sarpras.gedung.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                    <x-icon name="add" class="h-4 w-4" />
                    Tambah Gedung
                </a>
            </div>
        </div>

        {{-- Table Container --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between gap-4">
                <form method="GET" action="{{ route('admin.sarpras.gedung.index') }}" class="flex items-center gap-2 w-full max-w-sm">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari kode atau nama gedung..." class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <button type="submit" class="rounded-xl bg-gray-100 px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 transition">Cari</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead class="bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3.5">Kode</th>
                            <th class="px-6 py-3.5">Nama Gedung</th>
                            <th class="px-6 py-3.5 text-center">Jumlah Lantai</th>
                            <th class="px-6 py-3.5 text-center">Total Ruangan</th>
                            <th class="px-6 py-3.5 text-center">Status</th>
                            <th class="px-6 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($gedungList as $gedung)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-6 py-4 font-mono font-bold text-gray-900">{{ $gedung->kode_gedung }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900">{{ $gedung->nama_gedung }}</div>
                                    @if($gedung->deskripsi)
                                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $gedung->deskripsi }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">{{ $gedung->jumlah_lantai }} Lantai</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">
                                        {{ $gedung->ruangan_count }} Ruangan
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $gedung->is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $gedung->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <a href="{{ route('admin.sarpras.gedung.edit', $gedung) }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-900 font-semibold text-xs">Edit</a>
                                    <form action="{{ route('admin.sarpras.gedung.destroy', $gedung) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus gedung ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-900 font-semibold text-xs">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                    Belum ada data gedung yang terdaftar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($gedungList->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $gedungList->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
