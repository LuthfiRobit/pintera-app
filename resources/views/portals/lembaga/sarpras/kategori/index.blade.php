<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Kategori Aset & Inventaris</h1>
                <p class="text-xs text-gray-500 mt-0.5">Pengelompokan jenis barang sarana prasarana dan inventaris sekolah.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Sarpras <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kategori Aset</b>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            {{-- Form Tambah Kategori --}}
            <div class="md:col-span-1">
                <form action="{{ route('admin.sarpras.kategori.store') }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                    @csrf
                    <p class="font-display text-sm font-bold text-gray-900">Tambah Kategori Baru</p>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kode Kategori <span class="text-rose-500">*</span></label>
                        <input type="text" name="kode_kategori" value="{{ old('kode_kategori') }}" required placeholder="Contoh: ELK, MEB, KBM" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <x-input-error :messages="$errors->get('kode_kategori')" class="mt-1" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Kategori <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama_kategori" value="{{ old('nama_kategori') }}" required placeholder="Contoh: Elektronik & IT" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <x-input-error :messages="$errors->get('nama_kategori')" class="mt-1" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Deskripsi</label>
                        <textarea name="deskripsi" rows="2" placeholder="Keterangan kategori..." class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">{{ old('deskripsi') }}</textarea>
                    </div>

                    <x-primary-button type="submit" class="w-full justify-center">
                        Simpan Kategori
                    </x-primary-button>
                </form>
            </div>

            {{-- Table Kategori --}}
            <div class="md:col-span-2">
                <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
                                <th class="px-5 py-3">Kode</th>
                                <th class="px-5 py-3">Nama Kategori</th>
                                <th class="px-5 py-3 text-center">Total Aset</th>
                                <th class="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium">
                            @forelse($kategoriList as $k)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-5 py-3.5 font-mono font-bold text-brand-600">{{ $k->kode_kategori }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="font-semibold text-gray-900">{{ $k->nama_kategori }}</span>
                                        @if($k->deskripsi)
                                            <p class="text-xs text-gray-400 mt-0.5">{{ $k->deskripsi }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <x-badge tone="blue">{{ $k->aset_count }} Item</x-badge>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <form
                                            action="{{ route('admin.sarpras.kategori.destroy', $k) }}"
                                            method="POST"
                                            class="inline"
                                            x-data
                                            @submit.prevent="confirmDialog('Hapus Kategori Aset?', @js('Apakah Anda yakin ingin menghapus kategori ' . $k->nama_kategori . '?'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-rose-600 hover:underline">Hapus</button>
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
                        <div class="border-t border-gray-200 px-5 py-4">
                            {{ $kategoriList->links('pagination.tailadmin') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
