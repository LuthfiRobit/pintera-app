<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6" x-data="{ openMutasi: false }">
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
                <div class="flex items-center gap-3">
                    <h1 class="font-display text-xl font-bold text-gray-900">{{ $aset->nama_barang }}</h1>
                    <span class="rounded-full bg-gray-100 px-3 py-1 font-mono text-xs font-bold text-gray-700">{{ $aset->kode_inventaris }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">Kategori: {{ $aset->kategori->nama_kategori ?? '-' }} | Lokasi: {{ $aset->ruangan->nama_ruangan ?? '-' }}</p>
            </div>
            <div class="flex items-center gap-3">
                <button @click="openMutasi = true" class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-purple-700 transition">
                    <x-icon name="swap_horiz" class="h-4 w-4" />
                    Mutasi Lokasi Ruangan
                </button>
                <a href="{{ route('admin.sarpras.aset.edit', $aset) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition">
                    <x-icon name="edit" class="h-4 w-4" />
                    Edit
                </a>
            </div>
        </div>

        {{-- Detail Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Informasi Pokok</h2>
                
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Merk / Tipe</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->merk ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Tipe Pencatatan</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->tipe_pencatatan->label() }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Jumlah / Satuan</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->qty }} {{ $aset->satuan }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Kondisi</p>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold mt-0.5 {{ $aset->kondisi->badgeColor() }}">
                            {{ $aset->kondisi->label() }}
                        </span>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Sumber Perolehan</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->sumber_perolehan->label() }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Harga Perolehan</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->harga_perolehan ? 'Rp ' . number_format($aset->harga_perolehan, 0, ',', '.') : '-' }}</p>
                    </div>
                </div>

                @if($aset->spesifikasi)
                    <div class="pt-3 border-t border-gray-100 text-xs">
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Spesifikasi Detail</p>
                        <p class="text-gray-700 mt-1 whitespace-pre-line">{{ $aset->spesifikasi }}</p>
                    </div>
                @endif
            </div>

            <div class="md:col-span-1 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Foto Barang</h2>
                @if($aset->foto_barang_path)
                    <img src="{{ asset('storage/' . $aset->foto_barang_path) }}" alt="{{ $aset->nama_barang }}" class="w-full h-48 object-cover rounded-xl border border-gray-200">
                @else
                    <div class="flex flex-col items-center justify-center h-48 rounded-xl bg-gray-50 border border-dashed border-gray-200 text-gray-400 text-xs">
                        <x-icon name="image" class="h-8 w-8 mb-2 text-gray-300" />
                        Tidak ada foto
                    </div>
                @endif
            </div>
        </div>

        {{-- Riwayat Mutasi Table --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h2 class="font-display text-sm font-bold text-gray-900">Riwayat Mutasi & Perpindahan Lokasi</h2>
            </div>
            <table class="w-full text-left text-xs text-gray-600">
                <thead class="bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3.5">Tanggal</th>
                        <th class="px-6 py-3.5">Dari Ruangan</th>
                        <th class="px-6 py-3.5">Ke Ruangan</th>
                        <th class="px-6 py-3.5 text-center">Jumlah</th>
                        <th class="px-6 py-3.5">Alasan</th>
                        <th class="px-6 py-3.5">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    @forelse($aset->riwayatMutasi as $m)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="px-6 py-3.5">{{ $m->tanggal_mutasi->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-3.5">{{ $m->ruanganAsal->nama_ruangan ?? '-' }}</td>
                            <td class="px-6 py-3.5 font-semibold text-gray-900">{{ $m->ruanganTujuan->nama_ruangan ?? '-' }}</td>
                            <td class="px-6 py-3.5 text-center">{{ $m->qty_pindah }} unit</td>
                            <td class="px-6 py-3.5 text-gray-600">{{ $m->alasan_mutasi }}</td>
                            <td class="px-6 py-3.5">{{ $m->dilakukanOleh->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">Aset ini belum pernah dipindahkan lokasinya.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Modal Dialog Mutasi --}}
        <div x-show="openMutasi" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" style="display: none;">
            <div @click.away="openMutasi = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="font-display text-sm font-bold text-gray-900">Mutasi / Pindah Ruangan Aset</h3>
                    <button @click="openMutasi = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <form action="{{ route('admin.sarpras.mutasi.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="aset_barang_id" value="{{ $aset->id }}">

                    <div class="rounded-xl bg-gray-50 p-3 text-xs text-gray-600">
                        <strong>Lokasi Saat Ini:</strong> {{ $aset->ruangan->nama_ruangan ?? '-' }}<br>
                        <strong>Stok Tersedia:</strong> {{ $aset->qty }} {{ $aset->satuan }}
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Pindahkan Ke Ruangan <span class="text-rose-500">*</span></label>
                        <select name="ruangan_tujuan_id" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none">
                            <option value="">Pilih Ruangan Tujuan...</option>
                            @foreach($ruanganOptions as $r)
                                @if($r->id !== $aset->ruangan_id)
                                    <option value="{{ $r->id }}">{{ $r->nama_ruangan }} ({{ $r->gedung->nama_gedung ?? 'Gedung' }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Jumlah Dipindah <span class="text-rose-500">*</span></label>
                            <input type="number" name="qty_pindah" value="{{ $aset->tipe_pencatatan->value === 'unit' ? 1 : $aset->qty }}" min="1" max="{{ $aset->qty }}" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tanggal Mutasi <span class="text-rose-500">*</span></label>
                            <input type="date" name="tanggal_mutasi" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Alasan Pemindahan <span class="text-rose-500">*</span></label>
                        <textarea name="alasan_mutasi" rows="3" required placeholder="Contoh: Kebutuhan ujian komputer kelas 9..." class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                        <button type="button" @click="openMutasi = false" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                        <button type="submit" class="rounded-xl bg-purple-600 px-5 py-2 text-xs font-semibold text-white hover:bg-purple-700">Proses Mutasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
