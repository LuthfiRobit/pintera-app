<div x-show="activeTab === 'keringanan'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <h2 class="font-display text-lg font-bold text-gray-900">Keringanan Biaya</h2>
            <p class="text-sm text-gray-500">Kategori keringanan yang berlaku untuk siswa ini.</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-card">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Kategori</th>
                        <th class="px-6 py-3">Berlaku Dari</th>
                        <th class="px-6 py-3">Berlaku Sampai</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($keringanan as $item)
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $item->kategoriKeringanan->nama }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $item->berlaku_dari?->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $item->berlaku_sampai?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-6 py-3 text-right">
                                @can('siswa-keringanan.kelola')
                                    <form method="POST" action="{{ route('admin.siswa-keringanan.destroy', $item) }}" onsubmit="return confirm('Cabut keringanan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700">Cabut</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-6 text-center text-gray-400">Belum ada keringanan yang diberikan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('siswa-keringanan.kelola')
            <form method="POST" action="{{ route('admin.siswa.keringanan.store', $siswa) }}" class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card">
                @csrf
                <h3 class="mb-4 font-semibold text-gray-900">Tambah Keringanan</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Kategori Keringanan</label>
                        <select name="kategori_keringanan_id" required class="w-full rounded-xl border-gray-300 text-sm">
                            @foreach (\App\Domains\Keuangan\Models\KategoriKeringanan::where('lembaga_id', $siswa->lembaga_id)->orderBy('nama')->get() as $kategori)
                                <option value="{{ $kategori->id }}">{{ $kategori->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Berlaku Dari</label>
                        <input type="date" name="berlaku_dari" required class="w-full rounded-xl border-gray-300 text-sm" value="{{ now()->toDateString() }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Berlaku Sampai (opsional)</label>
                        <input type="date" name="berlaku_sampai" class="w-full rounded-xl border-gray-300 text-sm">
                    </div>
                </div>
                <button type="submit" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    Simpan
                </button>
            </form>
        @endcan
    </div>
</div>
