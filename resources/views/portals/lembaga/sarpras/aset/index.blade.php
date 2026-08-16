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
                <h1 class="font-display text-xl font-bold text-gray-900">Master Aset & Inventaris Barang</h1>
                <p class="text-xs text-gray-500 mt-1">Daftar inventaris sarana prasarana sekolah (Metode Barcode & Kuantitas Ruangan).</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.sarpras.mutasi.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition">
                    <x-icon name="swap_horiz" class="h-4 w-4" />
                    Riwayat Mutasi
                </a>
                <a href="{{ route('admin.sarpras.aset.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                    <x-icon name="add" class="h-4 w-4" />
                    Tambah Aset Baru
                </a>
            </div>
        </div>

        {{-- Filter & Search Table --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('admin.sarpras.aset.index') }}" class="flex flex-wrap items-center gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama barang, kode, merk..." class="w-full sm:w-64 rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    
                    <select name="kategori_id" class="rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">Semua Kategori</option>
                        @foreach($kategoriOptions as $k)
                            <option value="{{ $k->id }}" {{ request('kategori_id') == $k->id ? 'selected' : '' }}>{{ $k->nama_kategori }}</option>
                        @endforeach
                    </select>

                    <select name="ruangan_id" class="rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">Semua Ruangan</option>
                        @foreach($ruanganOptions as $r)
                            <option value="{{ $r->id }}" {{ request('ruangan_id') == $r->id ? 'selected' : '' }}>{{ $r->nama_ruangan }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 transition">Filter</button>
                    @if(request()->hasAny(['search', 'kategori_id', 'ruangan_id', 'kondisi']))
                        <a href="{{ route('admin.sarpras.aset.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead class="bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3.5">Kode Inventaris</th>
                            <th class="px-6 py-3.5">Nama Barang & Merk</th>
                            <th class="px-6 py-3.5">Kategori</th>
                            <th class="px-6 py-3.5">Lokasi Ruangan</th>
                            <th class="px-6 py-3.5 text-center">Jumlah</th>
                            <th class="px-6 py-3.5 text-center">Kondisi</th>
                            <th class="px-6 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        @forelse($asetList as $aset)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-6 py-4 font-mono font-bold text-gray-900">
                                    <a href="{{ route('admin.sarpras.aset.show', $aset) }}" class="text-indigo-600 hover:underline">
                                        {{ $aset->kode_inventaris }}
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900">{{ $aset->nama_barang }}</div>
                                    @if($aset->merk)
                                        <div class="text-[11px] text-gray-400">Merk: {{ $aset->merk }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">{{ $aset->kategori->nama_kategori ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-900">{{ $aset->ruangan->nama_ruangan ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $aset->ruangan->gedung->nama_gedung ?? '' }}</div>
                                </td>
                                <td class="px-6 py-4 text-center">{{ $aset->qty }} {{ $aset->satuan }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $aset->kondisi->badgeColor() }}">
                                        {{ $aset->kondisi->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <a href="{{ route('admin.sarpras.aset.show', $aset) }}" class="text-indigo-600 hover:underline font-semibold">Detail</a>
                                    <a href="{{ route('admin.sarpras.aset.edit', $aset) }}" class="text-indigo-600 hover:underline font-semibold">Edit</a>
                                    <form action="{{ route('admin.sarpras.aset.destroy', $aset) }}" method="POST" class="inline" onsubmit="return confirm('Hapus aset ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-900 font-semibold">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400">Belum ada aset terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($asetList->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $asetList->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
