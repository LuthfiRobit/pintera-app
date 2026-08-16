<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
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
                <h1 class="font-display text-xl font-bold text-gray-900">Kategori Aset & Inventaris</h1>
                <p class="text-xs text-gray-500 mt-1">Pengelompokan jenis barang sarana prasarana sekolah.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Form Tambah Kategori --}}
            <div class="md:col-span-1">
                <form action="{{ route('admin.sarpras.kategori.store') }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm space-y-4">
                    @csrf
                    <h2 class="font-display text-sm font-bold text-gray-900">Tambah Kategori Baru</h2>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kode Kategori <span class="text-rose-500">*</span></label>
                        <input type="text" name="kode_kategori" value="{{ old('kode_kategori') }}" required placeholder="Contoh: ELK, MEB, KBM" class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        @error('kode_kategori') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Nama Kategori <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama_kategori" value="{{ old('nama_kategori') }}" required placeholder="Contoh: Elektronik & IT" class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        @error('nama_kategori') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Deskripsi</label>
                        <textarea name="deskripsi" rows="2" placeholder="Keterangan kategori..." class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">{{ old('deskripsi') }}</textarea>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-indigo-700 transition">
                        Simpan Kategori
                    </button>
                </form>
            </div>

            {{-- Table Kategori --}}
            <div class="md:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="w-full text-left text-xs text-gray-600">
                        <thead class="bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3.5">Kode</th>
                                <th class="px-5 py-3.5">Nama Kategori</th>
                                <th class="px-5 py-3.5 text-center">Total Aset</th>
                                <th class="px-5 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium">
                            @forelse($kategoriList as $k)
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="px-5 py-3.5 font-mono font-bold text-gray-900">{{ $k->kode_kategori }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-gray-900">{{ $k->nama_kategori }}</div>
                                        @if($k->deskripsi)
                                            <div class="text-[11px] text-gray-400 mt-0.5">{{ $k->deskripsi }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">
                                            {{ $k->aset_count }} Item
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <form action="{{ route('admin.sarpras.kategori.destroy', $k) }}" method="POST" class="inline" onsubmit="return confirm('Hapus kategori ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-900 font-semibold text-xs">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-10 text-center text-gray-400">Belum ada kategori aset.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if($kategoriList->hasPages())
                        <div class="p-4 border-t border-gray-100">
                            {{ $kategoriList->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
